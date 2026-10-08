<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\BankAgiController;
use App\Interface\RedisServiceInterface;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use App\Services\Payment\BankAgiService;
use App\Services\Payment\OrderHistoryService;
use App\Services\Payment\PaymentService;
use App\Services\System\RedisService;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\Support\PaymentTestCase;

class BankAgiIntegrationTest extends PaymentTestCase
{
    private PaymentRepository $repository;

    private Project $project;

    private string $privateKey;

    private string $publicKey;

    private array $redisData = [];

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        Redis::shouldReceive('get')->andReturnUsing(fn (string $key) => $this->redisData[$key] ?? null);
        Redis::shouldReceive('setex')->andReturnUsing(function (string $key, int $ttl, mixed $value) {
            $this->redisData[$key] = $value;

            return true;
        });
        Redis::shouldReceive('set')->andReturn(true);
        Redis::shouldReceive('incr')->andReturnUsing(function (string $key) {
            return $this->redisData[$key] = ($this->redisData[$key] ?? 0) + 1;
        });
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('del')->andReturn(1);
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.bank_agi.allowed_hosts' => ['agi.test']]);

        $gateway = PaymentGateway::create(['key' => 'bank_agi', 'name' => 'Bank Artha Graha Internasional', 'description' => 'AGI QRIS']);
        $this->repository = PaymentRepository::create([
            'payment_gateway_id' => $gateway->getAttribute('id'),
            'key' => 'agi_sandbox',
            'mode' => 'sandbox',
            'value' => [
                'base_url' => 'https://agi.test:38065',
                'client_id' => 'partner-client-id',
                'client_secret' => base64_encode('agi-outbound-secret'),
                'private_key' => $this->privateKey,
                'bank_client_id' => 'bank-client-id',
                'bank_public_key' => $this->publicKey,
                'bank_client_secret' => base64_encode('agi-inbound-secret'),
                'merchant_id' => 'ID2024000000565',
                'merchant_user' => 'AGIMERCHANT',
                'channel_id' => '95221',
                'device_id' => '12345678',
                'channel' => 'API',
            ],
        ]);
        $this->project = Project::create([
            'name' => 'AGI Integration Test',
            'type' => 'AGIT',
            'slug' => 'bank_agi',
            'key' => 'agi-key',
            'secure' => 'agi-secure',
            'value' => 'agi-project-token',
            'callback' => 'https://merchant.test/payment-callback',
        ]);
    }

    private function fakeBank(): void
    {
        Http::fake([
            '*/api/v1/bisnap/access-token' => Http::response([
                'responseCode' => '2007300',
                'responseMessage' => 'Successful',
                'accessToken' => 'agi-bank-token',
            ]),
            '*/snap/api/v1.0/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2001700',
                'responseMessage' => 'SUCCEED',
                'referenceNo' => 'API000562794',
                'qrContent' => '000201010212AGIQRIS',
                'additionalInfo' => ['billNumber' => '7000000000000000000710730'],
            ]),
            '*/snap/api/v1.0/qr/qr-mpm-query' => Http::response([
                'responseCode' => '2001800',
                'responseMessage' => 'SUCCEED',
                'latestTransactionStatus' => '00',
            ]),
        ]);
    }

    private function createOrder(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/order/create', array_merge([
            'merchantOrderId' => 'INV-001',
            'paymentAmount' => 3500,
            'paymentMethod' => 'qris',
            'paymentRepositoryId' => $this->repository->getAttribute('id'),
            'mode' => 'sandbox',
        ], $overrides), ['Token' => $this->project->getValue()]);
    }

    private function tokenHeaders(?string $timestamp = null, string $clientId = 'bank-client-id'): array
    {
        $timestamp ??= now('UTC')->format('Y-m-d\TH:i:s.vP');
        openssl_sign($clientId.'|'.$timestamp, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [
            'X_CLIENT_KEY' => $clientId,
            'X_TIMESTAMP' => $timestamp,
            'X_SIGNATURE' => base64_encode($signature),
        ];
    }

    private function issueToken(): string
    {
        return $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $this->tokenHeaders())
            ->assertOk()->assertJsonPath('responseCode', '2007300')->json('accessToken');
    }

    private function notification(array $overrides = []): array
    {
        return array_replace_recursive([
            'originalReferenceNo' => 'API000562794',
            'latestTransactionStatus' => '00',
            'transactionStatusDesc' => 'Success',
            'amount' => ['value' => '3500.00', 'currency' => 'IDR'],
            'additionalInfo' => [
                'merchantId' => 'ID2024000000565',
                'merchantUser' => 'AGIMERCHANT',
                'billNumber' => '7000000000000000000710730',
            ],
        ], $overrides);
    }

    private function notify(array $payload, ?string $token = null, array $headers = [], bool $pretty = false): TestResponse
    {
        $token ??= $this->issueToken();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s.vP');
        $body = json_encode($payload, $pretty ? JSON_PRETTY_PRINT : 0);
        $minifiedBody = json_encode($payload);
        $signature = hash_hmac('sha512', 'POST:/api/bank/agi/notify-qris:'.$token.':'.hash('sha256', $minifiedBody).':'.$timestamp, 'agi-inbound-secret');
        $headers = array_merge([
            'Authorization' => 'Bearer '.$token,
            'X_PARTNER_ID' => 'bank-client-id',
            'X_TIMESTAMP' => $timestamp,
            'X_SIGNATURE' => $signature,
            'X_EXTERNAL_ID' => '20260930000000000001',
            'CHANNEL_ID' => '95221',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        return $this->call('POST', '/api/bank/agi/notify-qris', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    public function test_order_creation_persists_qris_and_sends_the_documented_signature(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk()->assertJsonPath('data.qr_string', '000201010212AGIQRIS');

        $this->assertDatabaseHas('orders', [
            'reference' => 'AGIT-INV-001',
            'payment_repository_id' => $this->repository->getAttribute('id'),
            'gateway_reference' => 'API000562794',
            'gateway_bill_number' => '7000000000000000000710730',
            'amount' => 3500,
            'status' => 'PENDING',
            'payment_method' => 'AGI_QRIS',
        ]);
        Http::assertSent(function (ClientRequest $request) {
            if (! str_ends_with($request->url(), '/api/v1/bisnap/access-token')) {
                return false;
            }

            return $request->header('X-CLIENT-KEY') === ['partner-client-id']
                && $request->header('X_CLIENT_KEY') === []
                && openssl_verify('partner-client-id|'.$request->header('X-TIMESTAMP')[0], base64_decode($request->header('X-SIGNATURE')[0]), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
        });
        Http::assertSent(function (ClientRequest $request) {
            if (! str_ends_with($request->url(), '/snap/api/v1.0/qr/qr-mpm-generate')) {
                return false;
            }
            $signature = hash_hmac('sha512', 'POST:/snap/api/v1.0/qr/qr-mpm-generate:agi-bank-token:'.hash('sha256', $request->body()).':'.$request->header('X-TIMESTAMP')[0], 'agi-outbound-secret');

            return $request->header('X-PARTNER-ID') === ['partner-client-id']
                && $request['merchantId'] === 'ID2024000000565'
                && $request['additionalInfo']['merchantUser'] === 'AGIMERCHANT'
                && $request['amount']['value'] === '3500.00'
                && $request->header('X-SIGNATURE')[0] === $signature
                && preg_match('/^\d{1,36}$/', $request->header('X-EXTERNAL-ID')[0]) === 1
                && $request->header('CHANNEL-ID')[0] === '95221';
        });
    }

    public function test_qris_rejection_rolls_back_the_order(): void
    {
        Http::fake([
            '*/api/v1/bisnap/access-token' => Http::response(['responseCode' => '2007300', 'accessToken' => 'token']),
            '*/qr-mpm-generate' => Http::response(['responseCode' => '4001701', 'responseMessage' => 'Invalid merchant']),
        ]);
        $this->createOrder()->assertStatus(400)->assertJsonPath('status', false);
        $this->assertDatabaseMissing('orders', ['reference' => 'AGIT-INV-001']);
    }

    public function test_duplicate_bank_bill_number_cannot_be_assigned_to_another_order(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $this->createOrder(['merchantOrderId' => 'INV-002'])->assertStatus(400)->assertJsonPath('status', false);
        $this->assertDatabaseMissing('orders', ['reference' => 'AGIT-INV-002']);
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'gateway_bill_number' => '7000000000000000000710730']);
    }

    public function test_foreign_gateway_repository_and_mode_mismatch_are_rejected(): void
    {
        $gateway = PaymentGateway::create(['key' => 'paprika', 'name' => 'Paprika', 'description' => 'Paprika']);
        $foreign = PaymentRepository::create(['payment_gateway_id' => $gateway->getAttribute('id'), 'key' => 'paprika-test', 'mode' => 'sandbox', 'value' => []]);
        $this->createOrder(['paymentRepositoryId' => $foreign->getAttribute('id')])->assertStatus(400)->assertJsonPath('status', false);
        $this->createOrder(['mode' => 'prod'])->assertStatus(400)->assertJsonPath('status', false);
        Http::assertNothingSent();
    }

    public function test_va_creation_is_rejected_without_calling_the_bank(): void
    {
        $this->createOrder(['paymentMethod' => 'VA_AGRAHA'])->assertStatus(400)->assertJsonPath('status', false);
        Http::assertNothingSent();
    }

    public function test_b2b_token_is_bound_to_the_agi_repository(): void
    {
        $token = $this->issueToken();
        $stored = json_decode($this->redisData['agi:snap_token:'.hash('sha256', $token)], true);
        $this->assertSame('bank-client-id', $stored['client_key']);
        $this->assertSame($this->repository->getAttribute('id'), $stored['repository_id']);
    }

    public function test_b2b_token_rejects_invalid_signature_unknown_client_and_expired_timestamp(): void
    {
        $headers = $this->tokenHeaders();
        $headers['X_SIGNATURE'] = base64_encode('forged-signature');
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $headers)
            ->assertStatus(401)->assertJsonPath('responseCode', '4017300');
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $this->tokenHeaders(clientId: 'unknown-client'))
            ->assertStatus(401)->assertJsonPath('responseCode', '4017300');
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $this->tokenHeaders(now()->subMinutes(10)->toIso8601String()))
            ->assertStatus(401)->assertJsonPath('responseCode', '4017300');
    }

    public function test_b2b_token_accepts_snap_hyphen_headers(): void
    {
        $headers = [];
        foreach ($this->tokenHeaders() as $key => $value) {
            $headers[str_replace('_', '-', $key)] = $value;
        }

        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $headers)
            ->assertOk()->assertJsonPath('responseCode', '2007300');
    }

    public function test_token_validation_uses_snap_error_codes(): void
    {
        $this->postJson('/api/bank/agi/access-token', [], $this->tokenHeaders())
            ->assertStatus(400)->assertJsonPath('responseCode', '4007302');
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'password'], $this->tokenHeaders())
            ->assertStatus(400)->assertJsonPath('responseCode', '4007301');
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'])
            ->assertStatus(400)->assertJsonPath('responseCode', '4007302');
    }

    public function test_notification_matches_the_bank_bill_and_dispatches_once_after_commit(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(), $token, pretty: true)->assertOk()->assertExactJson(['responseCode' => '2001900', 'responseMessage' => 'Success']);
        $this->notify($this->notification(), $token)->assertOk();

        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'SUCCESS']);
        Queue::assertPushed(SendMerchantCallback::class, 1);
        Queue::assertPushed(SendMerchantCallback::class, fn (SendMerchantCallback $job) => $job->afterCommit === true && $job->params['merchantOrderId'] === 'INV-001' && $job->params['status'] === 'SUCCESS');
        Queue::assertPushed(SendNotificationJob::class, 1);
        Queue::assertPushed(SendNotificationJob::class, fn (SendNotificationJob $job) => $job->afterCommit === true);
    }

    public function test_duplicate_success_still_verifies_the_signature_and_token(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(), $token)->assertOk();
        $this->notify($this->notification(), $token, ['X_SIGNATURE' => 'invalid'])->assertStatus(401)->assertJsonPath('responseCode', '4011900');
        $this->notify($this->notification(), 'unknown-token')->assertStatus(401)->assertJsonPath('responseCode', '4011901');
        Queue::assertPushed(SendMerchantCallback::class, 1);
    }

    public function test_invalid_signature_token_partner_timestamp_and_amount_do_not_mark_paid(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(), $token, ['X_SIGNATURE' => 'invalid'])->assertStatus(401);
        $this->notify($this->notification(), 'forged-token')->assertStatus(401);
        $this->notify($this->notification(), $token, ['X_PARTNER_ID' => 'other-partner'])->assertStatus(401);
        $this->notify($this->notification(), $token, ['X_TIMESTAMP' => now()->subMinutes(10)->toIso8601String()])->assertStatus(401);
        $this->notify($this->notification(['amount' => ['value' => '1.00']]), $token)->assertStatus(400)->assertJsonPath('responseCode', '4001901');
        $this->notify($this->notification(['additionalInfo' => ['merchantId' => 'OTHER']]), $token)->assertStatus(401);
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_callback_cannot_update_an_order_in_another_repository(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $other = PaymentRepository::create([
            'payment_gateway_id' => $this->repository->getPaymentGatewayId(),
            'key' => 'other-agi',
            'mode' => 'sandbox',
            'value' => array_replace($this->repository->getValue(), ['bank_client_id' => 'other-bank-client']),
        ]);
        $token = 'other-repository-token';
        $this->redisData['agi:snap_token:'.hash('sha256', $token)] = json_encode(['repository_id' => $other->getAttribute('id'), 'client_key' => 'other-bank-client']);
        $this->notify($this->notification(), $token, ['X_PARTNER_ID' => 'other-bank-client'])->assertStatus(404)->assertJsonPath('responseCode', '4041901');
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_pending_and_late_failure_notifications_preserve_paid_status(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(['latestTransactionStatus' => '03']), $token)->assertOk();
        Queue::assertNothingPushed();
        $this->notify($this->notification(), $token)->assertOk();
        $this->notify($this->notification(['latestTransactionStatus' => '06']), $token)->assertOk();
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'SUCCESS']);
        Queue::assertPushed(SendMerchantCallback::class, 1);
    }

    public function test_failed_notification_maps_status_without_treating_it_as_a_payment(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $this->notify($this->notification(['latestTransactionStatus' => '06']))->assertOk();
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'FAILED']);
        Queue::assertPushed(SendMerchantCallback::class, fn (SendMerchantCallback $job) => $job->params['status'] === 'FAILED' && $job->params['resultCode'] === '01');
    }

    public function test_merchant_and_client_status_queries_send_the_saved_bill_number(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $this->getJson('/api/order/checkOrderStatus?reference=AGIT-INV-001', ['Token' => $this->project->getValue()])
            ->assertOk()->assertJsonPath('data.latestTransactionStatus', '00');
        $clientToken = (new RedisService)->generatePaymentToken($this->project->getAttribute('id'), $this->project->getValue(), 'AGIT-INV-001');
        $this->getJson('/api/client/order/checkOrderStatus?reference=AGIT-INV-001', ['Token' => $clientToken])
            ->assertOk()->assertJsonPath('data.latestTransactionStatus', '00');
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/qr-mpm-query') && $request['serviceCode'] === '17'
            && $request['originalReferenceNo'] === 'API000562794'
            && $request['additionalInfo']['billNumber'] === '7000000000000000000710730');
    }

    public function test_client_checkout_updates_the_existing_order(): void
    {
        $this->fakeBank();
        $order = Order::create([
            'id' => 'AGI-CLIENT-001',
            'reference' => 'AGIT-CLIENT-001',
            'type' => $this->project->getType(),
            'mode' => 'sandbox',
            'status' => OrderStatus::PENDING,
            'amount' => 3500,
        ]);
        $token = (new RedisService)->generatePaymentToken($this->project->getAttribute('id'), $this->project->getValue(), $order->getReference());
        $this->postJson('/api/client/order/createPayment', [
            'reference' => $order->getReference(),
            'paymentMethod' => 'qris',
            'paymentRepositoryId' => $this->repository->getAttribute('id'),
        ], ['Token' => $token])->assertOk()->assertJsonPath('data.reference', 'AGIT-CLIENT-001');
        $this->assertSame(1, Order::where('reference', 'AGIT-CLIENT-001')->count());
        $this->assertSame('000201010212AGIQRIS', $order->fresh()->getValue());
    }

    public function test_version_two_returns_checkout_link_and_backoffice_defaults_to_qris(): void
    {
        $this->fakeBank();
        config(['app.payment_url' => 'https://checkout.test']);
        $response = $this->createOrder(['version' => '2'])->assertOk();
        $this->assertStringStartsWith('https://checkout.test/detailpayment?token=', $response->json('data.link'));
        $this->assertStringContainsString('reference=AGIT-INV-001', $response->json('data.link'));
        $testData = (new PaymentService)->testCreateOrder($this->repository);
        $this->assertSame('qris', $testData['request']->input('paymentMethod'));
    }

    public function test_va_route_returns_unsupported_and_cannot_change_qris_orders(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $this->postJson('/api/bank/agi/notify-va', $this->notification())->assertStatus(501)->assertJsonPath('responseCode', '5012500');
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_notification_database_failure_rolls_back_and_does_not_dispatch(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $history = Mockery::mock(OrderHistoryService::class);
        $history->shouldReceive('log')->once()->andThrow(new RuntimeException('History database unavailable'));
        $this->app->instance(BankAgiController::class, new BankAgiController(new BankAgiService(orderHistoryService: $history)));

        $this->notify($this->notification(), $token)->assertStatus(500)->assertExactJson([
            'responseCode' => '5001901',
            'responseMessage' => 'Internal Server Error',
        ]);
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_missing_or_expired_token_does_not_update_the_order(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        unset($this->redisData['agi:snap_token:'.hash('sha256', $token)]);
        $this->notify($this->notification(), $token)->assertStatus(401)->assertJsonPath('responseCode', '4011901');
        $this->notify($this->notification(), $token, ['Authorization' => ''])->assertStatus(401)->assertJsonPath('responseCode', '4011903');
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
    }

    public function test_repeated_checkout_cannot_generate_a_second_qris(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = (new RedisService)->generatePaymentToken($this->project->getAttribute('id'), $this->project->getValue(), 'AGIT-INV-001');
        $this->postJson('/api/client/order/createPayment', ['reference' => 'AGIT-INV-001', 'paymentMethod' => 'qris'], ['Token' => $token])
            ->assertStatus(400)->assertJsonPath('status', false);
        Http::assertSentCount(2);
    }

    public function test_seeders_and_gateway_reference_migration_are_repeatable(): void
    {
        $this->seed(PaymentGatewaySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
        $this->assertSame(1, PaymentGateway::where('key', 'bank_agi')->count());
        $this->assertDatabaseHas('payment_methods', ['key' => 'AGI_QRIS', 'payment_gateway_id' => $this->repository->getPaymentGatewayId()]);

        $migration = require database_path('migrations/2026_09_30_000001_add_gateway_references_to_orders_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'gateway_bill_number'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'gateway_bill_number'));
        $this->assertTrue(Schema::hasIndex('orders', 'orders_gateway_bill_number_index'));
    }

    public function test_checkout_and_merchant_order_details_do_not_expose_credentials(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $merchant = $this->getJson('/api/order/detail?reference=AGIT-INV-001', ['Token' => $this->project->getValue()])->assertOk();
        $token = (new RedisService)->generatePaymentToken($this->project->getAttribute('id'), $this->project->getValue(), 'AGIT-INV-001');
        $checkout = $this->getJson('/api/client/order/detail?reference=AGIT-INV-001', ['Token' => $token])->assertOk();

        foreach ([$merchant, $checkout] as $response) {
            $response->assertJsonMissingPath('data.payment_repository.value');
            $response->assertJsonMissingPath('data.project.value');
            $response->assertJsonMissingPath('data.project.key');
            $response->assertJsonMissingPath('data.project.secure');
            $this->assertStringNotContainsString($this->privateKey, $response->getContent());
            $this->assertStringNotContainsString($this->project->getValue(), $response->getContent());
        }
    }

    public function test_merchant_cannot_read_or_overwrite_bank_credentials(): void
    {
        $path = '/api/payment-repositories/'.$this->repository->getAttribute('id');
        $this->getJson($path, ['Token' => $this->project->getValue()])->assertUnauthorized();
        $this->putJson($path, ['value' => ['bank_client_secret' => 'attacker-secret']], ['Token' => $this->project->getValue()])->assertUnauthorized();
        $this->assertSame($this->repository->getValue(), $this->repository->fresh()->getValue());
    }

    public function test_fractional_amount_cannot_be_rounded_into_a_matching_payment(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(['amount' => ['value' => '3499.999']]), $token)
            ->assertStatus(400)->assertJsonPath('responseCode', '4001901');
        $this->notify($this->notification(['amount' => ['value' => '3.5e3']]), $token)
            ->assertStatus(400)->assertJsonPath('responseCode', '4001901');
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_order_amount_with_more_than_two_decimals_is_rejected_before_bank_calls(): void
    {
        $this->fakeBank();
        $this->createOrder(['paymentAmount' => '3500.001'])->assertStatus(400);
        Http::assertNothingSent();
        $this->assertSame(0, Order::count());
    }

    public function test_oversized_notification_is_rejected_without_processing_the_order(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $this->notify($this->notification(['extra' => str_repeat('x', 65536)]))
            ->assertStatus(413)->assertJsonPath('responseCode', '4131900');
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        Queue::assertNothingPushed();
    }

    public function test_bank_url_with_user_credentials_is_rejected_before_network_calls(): void
    {
        $this->fakeBank();
        $this->repository->setValue(array_replace($this->repository->getValue(), ['base_url' => 'https://user:password@agi.test:38065']));
        $this->repository->save();
        $this->createOrder()->assertStatus(400);
        Http::assertNothingSent();
    }

    public function test_admin_can_still_read_and_update_repository_credentials(): void
    {
        Sanctum::actingAs(new User(['name' => 'Admin', 'role' => UserRole::ADMIN]));
        $path = '/api/admin/payment-repositories/'.$this->repository->getAttribute('id');
        $this->getJson($path)->assertOk()->assertJsonPath('data.value.client_id', 'partner-client-id');
        $this->getJson('/api/admin/payment-repositories')->assertOk()->assertJsonPath('data.0.value.client_id', 'partner-client-id');
        $config = array_replace($this->repository->getValue(), ['merchant_user' => 'UPDATED-MERCHANT']);
        $this->putJson($path, ['value' => $config])->assertOk()->assertJsonPath('data.value.merchant_user', 'UPDATED-MERCHANT');
        $this->assertSame('UPDATED-MERCHANT', $this->repository->fresh()->getValue()['merchant_user']);
        $this->assertArrayNotHasKey('value', $this->repository->toArray());
    }

    public function test_non_admin_sanctum_user_cannot_manage_credentials(): void
    {
        Sanctum::actingAs(new User(['name' => 'Merchant', 'role' => UserRole::USER]));
        foreach (['/api/admin/payment-repositories/', '/api/payment-repositories/'] as $prefix) {
            $path = $prefix.$this->repository->getAttribute('id');
            $this->getJson($path)->assertForbidden();
            $this->putJson($path, ['value' => []])->assertForbidden();
        }
    }

    public function test_outbound_tokens_are_cached_briefly_and_invalidated_on_credential_rotation(): void
    {
        Http::fake(['*/api/v1/bisnap/access-token' => Http::sequence()
            ->push(['responseCode' => '2007300', 'accessToken' => 'first-token', 'expiresIn' => '900'])
            ->push(['responseCode' => '2007300', 'accessToken' => 'second-token', 'expiresIn' => '900'])
            ->push(['responseCode' => '2007300', 'accessToken' => 'third-token', 'expiresIn' => '900']),
        ]);
        $service = new BankAgiService;
        $this->assertSame('first-token', $service->getB2BToken($this->repository)['accessToken']);
        $this->assertSame('first-token', $service->getB2BToken($this->repository)['accessToken']);
        Http::assertSentCount(1);
        $this->repository->setValue(array_replace($this->repository->getValue(), ['client_secret' => base64_encode('rotated-secret')]));
        $this->assertSame('second-token', $service->getB2BToken($this->repository)['accessToken']);
        Http::assertSentCount(2);
        $this->travel(61)->seconds();
        $this->assertSame('third-token', $service->getB2BToken($this->repository)['accessToken']);
        Http::assertSentCount(3);
        $this->travelBack();
    }

    public function test_token_cache_never_exceeds_short_bank_expiry(): void
    {
        Http::fake(['*/api/v1/bisnap/access-token' => Http::sequence()
            ->push(['responseCode' => '2007300', 'accessToken' => 'short-token', 'expiresIn' => '6'])
            ->push(['responseCode' => '2007300', 'accessToken' => 'fresh-token', 'expiresIn' => '6']),
        ]);
        $service = new BankAgiService;
        $this->assertSame('short-token', $service->getB2BToken($this->repository)['accessToken']);
        $this->travel(2)->seconds();
        $this->assertSame('fresh-token', $service->getB2BToken($this->repository)['accessToken']);
        Http::assertSentCount(2);
        $this->travelBack();
    }

    public function test_optional_outbound_token_cache_failure_does_not_block_bank_requests(): void
    {
        Http::fake(['*/api/v1/bisnap/access-token' => Http::response(['responseCode' => '2007300', 'accessToken' => 'bank-token', 'expiresIn' => '900'])]);
        $redis = Mockery::mock(RedisServiceInterface::class);
        $redis->shouldReceive('get')->once()->andThrow(new RuntimeException('Redis unavailable'));
        $redis->shouldReceive('set')->once()->andThrow(new RuntimeException('Redis unavailable'));
        $this->assertSame('bank-token', (new BankAgiService(redisService: $redis))->getB2BToken($this->repository)['accessToken']);
    }

    public function test_bank_requests_do_not_follow_redirects(): void
    {
        Http::fake(function (ClientRequest $request, array $options) {
            $this->assertFalse($options['allow_redirects']);

            return str_ends_with($request->url(), '/access-token')
                ? Http::response(['responseCode' => '2007300', 'accessToken' => 'bank-token'])
                : Http::response('', 302, ['Location' => 'https://attacker.test/collect']);
        });
        $this->createOrder()->assertStatus(400);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'attacker.test'));
        $this->assertSame(0, Order::count());
    }

    public function test_unapproved_bank_hosts_and_oversized_headers_are_rejected(): void
    {
        $this->fakeBank();
        foreach (['https://127.0.0.1', 'https://attacker.test', 'https://agi.test.attacker.test'] as $url) {
            $this->repository->setValue(array_replace($this->repository->getValue(), ['base_url' => $url]));
            $this->repository->save();
            $this->createOrder()->assertStatus(400);
        }
        Http::assertNothingSent();
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], array_replace($this->tokenHeaders(), ['X_SIGNATURE' => str_repeat('x', 2049)]))
            ->assertStatus(400)->assertJsonPath('responseCode', '4007301');
    }

    public function test_duplicate_notification_avoids_loading_unused_order_relations(): void
    {
        $this->fakeBank();
        $this->createOrder()->assertOk();
        $token = $this->issueToken();
        $this->notify($this->notification(), $token)->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->notify($this->notification(), $token)->assertOk();
        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        $this->assertStringNotContainsString('from "payment_methods"', $queries);
        $this->assertStringNotContainsString('from "projects"', $queries);
        Queue::assertPushed(SendMerchantCallback::class, 1);
    }

    public function test_invalid_calendar_timestamp_is_rejected_even_when_carbon_could_normalize_it(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0, 0));
        $this->postJson('/api/bank/agi/access-token', ['grantType' => 'client_credentials'], $this->tokenHeaders('2026-09-31T12:00:00Z'))
            ->assertStatus(400)->assertJsonPath('responseCode', '4007301');
        $this->travelBack();
    }

    public function test_signed_callback_uses_exact_decimal_amount_sent_to_the_bank(): void
    {
        $this->fakeBank();
        $amount = '9999999999999.99';
        $this->createOrder(['paymentAmount' => $amount])->assertOk();
        Http::assertSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/qr-mpm-generate') && $request['amount']['value'] === $amount);
        $this->notify($this->notification(['amount' => ['value' => '9999999999999.98']]))->assertStatus(400);
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'PENDING']);
        $this->notify($this->notification(['amount' => ['value' => $amount]]))->assertOk();
        $this->assertDatabaseHas('orders', ['reference' => 'AGIT-INV-001', 'status' => 'SUCCESS']);
    }
}
