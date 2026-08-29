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

    private string $clientKey = 'test-client-key-cb';
    private string $clientSecret = 'test-client-secret-cb';

    protected function setUp(): void
    {
        parent::setUp();

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

    private function generateCallbackSignature(array $body): array
    {
        $timestamp = '2026-08-24T12:00:00+00:00';
        $requestBody = json_encode($body);
        $bodyHash = hash('sha256', $requestBody);
        $stringToSign = "POST:/api/callback/paprika:{$this->clientKey}:{$bodyHash}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));

        return [
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
}
