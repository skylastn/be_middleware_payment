<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Http\Helper\LogHelper;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaprikaCallbackTest extends TestCase
{
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private Project $project;

    private string $clientKey = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
    private string $clientSecret = 'test-client-secret-cb';
    private string $privateKey = '';
    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $res = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($res)['key'];

        $this->gateway = PaymentGateway::create([
            'key' => 'paprika',
            'name' => 'Paprika',
            'description' => 'Paprika Payment Gateway',
        ]);

        $this->repo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_sandbox_cb_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key' => $this->clientKey,
                'api_secret' => $this->clientSecret,
                'public_key' => $this->publicKey,
                'private_key' => $this->privateKey,
                'base_url' => 'https://sandbox.paprika.test',
            ],
        ]);

        $this->project = Project::create([
            'name' => 'Paprika Test Project',
            'type' => 'AD',
            'slug' => 'paprika',
            'key' => 'testkey1234',
            'secure' => 'testsecure1234567890',
            'value' => Str::random(60),
            'callback' => 'https://example.com/callback',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->project && $this->project->exists) {
            $this->project->delete();
        }
        if ($this->repo && $this->repo->exists) {
            $this->repo->forceDelete();
        }
        if ($this->gateway && $this->gateway->exists) {
            $this->gateway->forceDelete();
        }
        parent::tearDown();
    }

    private function generateCallbackSignature(array $body, string $path = '/api/callback/paprika', ?string $token = 'test-bearer-token'): array
    {
        // Store test token in redis so bearer token validation passes
        $redis = new \App\Services\System\RedisService;
        $redis->storeSnapAccessToken($token, $this->clientKey, 900);

        $timestamp = '2026-08-24T12:00:00+00:00';
        $requestBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $requestBody);
        $stringToSign = "POST:{$path}:{$token}:{$bodyHash}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));

        return [
            'Authorization' => "Bearer {$token}",
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-PARTNER-ID' => $this->clientKey,
        ];
    }

    public function test_callback_paprika_success_updates_order_status(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00201',
            'type' => 'AD',
            'reference' => 'AD-FM-0000100',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{"partnerReferenceNo":"AD-FM-0000100"}',
        ]);

        Queue::fake();

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000100',
                'originalReferenceNo' => 'AD-FM-0000100',
                'latestTransactionStatus' => '00',
                'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            ];
            $headers = $this->generateCallbackSignature($body);

            $response = $this->postJson('/api/callback/paprika', $body, $headers);

            LogHelper::sendLog('test_callback_paprika_success', $response->json());
            $response->assertStatus(200);
            $response->assertJson([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());

            Queue::assertPushed(SendMerchantCallback::class);
            Queue::assertPushed(SendNotificationJob::class);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_paprika_failed_updates_order_status(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00202',
            'type' => 'AD',
            'reference' => 'AD-FM-0000101',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{"partnerReferenceNo":"AD-FM-0000101"}',
        ]);

        Queue::fake();

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000101',
                'originalReferenceNo' => 'AD-FM-0000101',
                'latestTransactionStatus' => '06',
                'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            ];
            $headers = $this->generateCallbackSignature($body);

            $response = $this->postJson('/api/callback/paprika', $body, $headers);

            $response->assertStatus(200);

            $order->refresh();
            $this->assertEquals(OrderStatus::FAILED, $order->getStatus());

            Queue::assertPushed(SendMerchantCallback::class);
            Queue::assertPushed(SendNotificationJob::class);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_paprika_missing_order_returns_error(): void
    {
        Queue::fake();

        $body = [
            'originalPartnerReferenceNo' => 'NONEXISTENT',
            'originalReferenceNo' => 'NONEXISTENT',
            'latestTransactionStatus' => '00',
        ];
        $headers = $this->generateCallbackSignature($body);

        $response = $this->postJson('/api/callback/paprika', $body, $headers);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
        ]);
    }

    public function test_callback_paprika_missing_fields_returns_error(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00203',
            'type' => 'AD',
            'reference' => 'AD-FM-0000102',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000102',
                'originalReferenceNo' => 'AD-FM-0000102',
            ];
            $headers = $this->generateCallbackSignature($body);

            $response = $this->postJson('/api/callback/paprika', $body, $headers);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => false,
            ]);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_paprika_invalid_signature_returns_error(): void
    {
        $redis = new \App\Services\System\RedisService;
        $redis->storeSnapAccessToken('test-bearer-token', $this->clientKey, 900);

        $order = Order::create([
            'id' => date('Ymd') . '-00204',
            'type' => 'AD',
            'reference' => 'AD-FM-0000103',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000103',
                'originalReferenceNo' => 'AD-FM-0000103',
                'latestTransactionStatus' => '00',
            ];

            $response = $this->postJson('/api/callback/paprika', $body, [
                'Authorization' => 'Bearer test-bearer-token',
                'X-SIGNATURE' => 'invalid-signature-value',
                'X-TIMESTAMP' => '2026-08-24T12:00:00+00:00',
                'X-PARTNER-ID' => $this->clientKey,
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => false,
            ]);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_paprika_missing_signature_headers_returns_error(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00205',
            'type' => 'AD',
            'reference' => 'AD-FM-0000104',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000104',
                'originalReferenceNo' => 'AD-FM-0000104',
                'latestTransactionStatus' => '00',
            ];

            $response = $this->postJson('/api/callback/paprika', $body);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => false,
            ]);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_paprika_idempotent_on_terminal_status(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00206',
            'type' => 'AD',
            'reference' => 'AD-FM-0000105',
            'status' => OrderStatus::SUCCESS,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
            'callback' => json_encode(['previous' => 'callback']),
        ]);

        Queue::fake();

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000105',
                'originalReferenceNo' => 'AD-FM-0000105',
                'latestTransactionStatus' => '00',
                'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            ];
            $headers = $this->generateCallbackSignature($body);

            $response = $this->postJson('/api/callback/paprika', $body, $headers);

            $response->assertStatus(200);
            $response->assertJson([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());

            $callbackData = json_decode($order->getCallback(), true);
            $this->assertEquals(['previous' => 'callback'], $callbackData);

            Queue::assertNotPushed(SendMerchantCallback::class);
            Queue::assertNotPushed(SendNotificationJob::class);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_snap_access_token_b2b_success(): void
    {
        $timestamp = now()->toIso8601String();
        $stringToSign = "{$this->clientKey}|{$timestamp}";
        $privateKey = openssl_pkey_get_private($this->privateKey);
        openssl_sign($stringToSign, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($rawSignature);

        $response = $this->postJson('/api/paprika/snap/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ], [
            'X-CLIENT-KEY' => $this->clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'tokenType' => 'Bearer',
            'expiresIn' => '900',
        ]);
        $this->assertNotEmpty($response->json('accessToken'));
    }

    public function test_snap_access_token_b2b_missing_headers(): void
    {
        $response = $this->postJson('/api/paprika/snap/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'responseCode' => '4007302',
        ]);
    }

    public function test_snap_access_token_b2b_invalid_signature(): void
    {
        $timestamp = now()->toIso8601String();
        $response = $this->postJson('/api/paprika/snap/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ], [
            'X-CLIENT-KEY' => $this->clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => base64_encode('invalid-signature'),
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'responseCode' => '4017300',
        ]);
    }

    public function test_snap_transfer_va_payment_with_bearer_token_success(): void
    {
        // 1. Request access token
        $timestamp = now()->toIso8601String();
        $stringToSign = "{$this->clientKey}|{$timestamp}";
        $privateKey = openssl_pkey_get_private($this->privateKey);
        openssl_sign($stringToSign, $rawSignature, $privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($rawSignature);

        $tokenResponse = $this->postJson('/api/paprika/snap/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ], [
            'X-CLIENT-KEY' => $this->clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ]);
        $token = $tokenResponse->json('accessToken');

        // 2. Create order
        $order = Order::create([
            'id' => date('Ymd') . '-00207',
            'type' => 'AD',
            'reference' => 'AD-FM-0000106',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'url' => '1234567890123456',
            'value' => '1234567890123456',
            'request' => '{}',
        ]);

        Queue::fake();

        try {
            $body = [
                'virtualAccountNo' => '1234567890123456',
                'trxId' => 'AD-FM-0000106',
                'paidAmount' => ['value' => '50000.00', 'currency' => 'IDR'],
            ];
            $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
            $bodyHash = hash('sha256', $bodyJson);
            $cbTimestamp = now()->toIso8601String();
            $path = '/api/callback/paprika';
            $stringToSignCb = "POST:{$path}:{$token}:{$bodyHash}:{$cbTimestamp}";
            $cbSignature = base64_encode(hash_hmac('sha512', $stringToSignCb, $this->clientSecret, true));

            $response = $this->postJson($path, $body, [
                'Authorization' => "Bearer {$token}",
                'X-PARTNER-ID' => $this->clientKey,
                'X-TIMESTAMP' => $cbTimestamp,
                'X-SIGNATURE' => $cbSignature,
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());
        } finally {
            $order->forceDelete();
        }
    }

    public function test_webhook_paprika_endpoint_for_qris_success(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00208',
            'type' => 'AD',
            'reference' => 'AD-FM-0000107',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{"partnerReferenceNo":"AD-FM-0000107"}',
        ]);

        Queue::fake();

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000107',
                'originalReferenceNo' => 'AD-FM-0000107',
                'latestTransactionStatus' => '00',
                'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            ];
            $headers = $this->generateCallbackSignature($body, '/api/paprika/webhook');

            $response = $this->postJson('/api/paprika/webhook', $body, $headers);

            $response->assertStatus(200);
            $response->assertJson([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());
        } finally {
            $order->forceDelete();
        }
    }

    public function test_paprika_controller_group_routes_work(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00209',
            'type' => 'AD',
            'reference' => 'AD-FM-0000108',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{"partnerReferenceNo":"AD-FM-0000108"}',
        ]);

        Queue::fake();

        try {
            $body = [
                'originalPartnerReferenceNo' => 'AD-FM-0000108',
                'originalReferenceNo' => 'AD-FM-0000108',
                'latestTransactionStatus' => '00',
                'amount' => ['value' => '100000.00', 'currency' => 'IDR'],
            ];
            $headers = $this->generateCallbackSignature($body, '/api/paprika/callback');

            $response = $this->postJson('/api/paprika/callback', $body, $headers);

            $response->assertStatus(200);
            $response->assertJson([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());
        } finally {
            $order->forceDelete();
        }
    }
}
