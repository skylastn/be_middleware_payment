<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Payment\PaprikaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaprikaServiceTest extends TestCase
{
    private PaprikaService $service;
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PaprikaService();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->publicKeyPem = openssl_pkey_get_details($keyPair)['key'];
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;

        $this->gateway = PaymentGateway::create([
            'key' => 'paprika',
            'name' => 'Paprika Unit',
            'description' => 'Paprika Payment Gateway',
        ]);

        $this->repo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_sandbox_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key' => 'test-client-key-12345',
                'api_secret' => 'test-client-secret-67890',
                'base_url' => 'https://sandbox.paprika.test',
                'private_key' => $this->privateKeyPem,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->repo && $this->repo->exists) {
            $this->repo->forceDelete();
        }
        if ($this->gateway && $this->gateway->exists) {
            $this->gateway->forceDelete();
        }
        parent::tearDown();
    }

    public function test_daily_unique_generates_deterministic_id(): void
    {
        $result1 = $this->service->dailyUnique('REF-001');
        $result2 = $this->service->dailyUnique('REF-001');

        $this->assertSame($result1, $result2);
        $this->assertIsString($result1);
        $this->assertNotEmpty($result1);
    }

    public function test_daily_unique_respects_length(): void
    {
        $result5 = $this->service->dailyUnique('REF-001', 5);
        $result10 = $this->service->dailyUnique('REF-001', 10);
        $result20 = $this->service->dailyUnique('REF-001', 20);

        $this->assertSame(5, strlen($result5));
        $this->assertSame(10, strlen($result10));
        $this->assertSame(20, strlen($result20));
    }

    public function test_daily_unique_rejects_invalid_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->dailyUnique('REF-001', 0);
    }

    public function test_daily_unique_rejects_length_over_36(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->dailyUnique('REF-001', 37);
    }

    public function test_generate_signature_returns_required_keys(): void
    {
        $result = $this->service->generateSignature($this->repo, '2026-08-24T00:00:00+00:00');

        $this->assertArrayHasKey('X-CLIENT-KEY', $result);
        $this->assertArrayHasKey('X-TIMESTAMP', $result);
        $this->assertArrayHasKey('X-SIGNATURE', $result);
        $this->assertSame('test-client-key-12345', $result['X-CLIENT-KEY']);
        $this->assertSame('2026-08-24T00:00:00+00:00', $result['X-TIMESTAMP']);
        $this->assertNotEmpty($result['X-SIGNATURE']);
    }

    public function test_generate_signature_uses_default_timestamp(): void
    {
        $result = $this->service->generateSignature($this->repo);

        $this->assertNotEmpty($result['X-TIMESTAMP']);
        $this->assertNotEmpty($result['X-SIGNATURE']);
    }

    public function test_sign_by_asymmetric_signature(): void
    {
        $stringToSign = 'test-string-to-sign';
        $signature = $this->service->signByAsymmetricSignature($stringToSign, $this->privateKeyPem);

        $this->assertNotEmpty($signature);
        $this->assertIsString($signature);

        $decodedSignature = base64_decode($signature);
        $this->assertSame(1, openssl_verify($stringToSign, $decodedSignature, $this->publicKeyPem, OPENSSL_ALGO_SHA256));
    }

    public function test_get_payment_repo_by_id(): void
    {
        $result = $this->service->getPaymentRepo(null, $this->repo->id);

        $this->assertNotNull($result);
        $this->assertSame($this->repo->id, $result->id);
    }

    public function test_get_payment_repo_by_mode_fallback(): void
    {
        $result = $this->service->getPaymentRepo(PaymentModeType::sandbox, null);

        $this->assertNotNull($result);
        $this->assertStringContainsString('paprika', $result->payment_gateway->key);
    }

    public function test_callback_throws_when_order_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $request = new Request([
            'originalPartnerReferenceNo' => 'NONEXISTENT',
            'originalReferenceNo' => 'NONEXISTENT',
            'latestTransactionStatus' => '00',
        ]);

        $this->service->callback($request);
    }

    public function test_callback_throws_on_missing_required_fields(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00901',
            'type' => 'AD',
            'reference' => 'AD-FM-0000071',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Missing required field: latestTransactionStatus');

            $request = new Request([
                'originalPartnerReferenceNo' => 'FM-0000071',
                'originalReferenceNo' => 'AD-FM-0000071',
            ]);

            $this->service->callback($request);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_idempotent_on_success_order(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00902',
            'type' => 'AD',
            'reference' => 'AD-FM-0000071',
            'status' => OrderStatus::SUCCESS,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $request = new Request([
                'originalPartnerReferenceNo' => 'FM-0000071',
                'originalReferenceNo' => 'AD-FM-0000071',
                'latestTransactionStatus' => '00',
            ]);

            $response = $this->service->callback($request);

            $this->assertEquals(200, $response->getStatusCode());
            $responseJson = json_decode($response->getContent(), true);
            $this->assertEquals('2002600', $responseJson['responseCode']);
            $this->assertEquals('Success', $responseJson['responseMessage']);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_throws_on_invalid_signature(): void
    {
        $order = Order::create([
            'id' => date('Ymd') . '-00903',
            'type' => 'AD',
            'reference' => 'AD-FM-0000072',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        try {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Invalid callback signature');

            $redis = new \App\Services\System\RedisService;
            $redis->storeSnapAccessToken('valid-token', 'test-client-key-12345', 900);

            $body = [
                'originalPartnerReferenceNo' => 'FM-0000072',
                'originalReferenceNo' => 'AD-FM-0000072',
                'latestTransactionStatus' => '00',
            ];

            $request = Request::create('/api/callback/paprika', 'POST',
                [], [], [],
                [
                    'HTTP_AUTHORIZATION' => 'Bearer valid-token',
                    'HTTP_X_SIGNATURE' => 'invalid-signature',
                    'HTTP_X_TIMESTAMP' => '2026-08-24T00:00:00+00:00',
                    'HTTP_X_PARTNER_ID' => 'test-client-key-12345',
                    'CONTENT_TYPE' => 'application/json',
                ],
                json_encode($body)
            );

            $this->service->callback($request);
        } finally {
            $order->forceDelete();
        }
    }

    public function test_callback_valid_signature_passes_verification(): void
    {
        $clientSecret = 'test-client-secret-67890';
        $clientKey = 'test-client-key-12345';
        $timestamp = '2026-08-24T00:00:00+00:00';

        $body = [
            'originalPartnerReferenceNo' => 'FM-0000073',
            'originalReferenceNo' => 'AD-FM-0000073',
            'latestTransactionStatus' => '00',
        ];

        $requestBody = json_encode($body);
        $bodyHash = hash('sha256', $requestBody);
        $token = 'test-unit-token';
        $redis = new \App\Services\System\RedisService;
        $redis->storeSnapAccessToken($token, $clientKey, 900);

        $stringToSign = "POST:/api/callback/paprika:{$token}:{$bodyHash}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));

        $order = Order::create([
            'id' => date('Ymd') . '-00904',
            'type' => 'AD',
            'reference' => 'AD-FM-0000073',
            'status' => OrderStatus::PENDING,
            'mode' => PaymentModeType::sandbox,
            'payment_repository_id' => $this->repo->id,
            'request' => '{}',
        ]);

        $project = Project::create([
            'name' => 'Test Project',
            'type' => 'AD',
            'slug' => 'paprika',
            'key' => 'testkey904',
            'secure' => 'testsecure904',
            'value' => Str::random(60),
            'callback' => 'https://example.com/callback',
        ]);

        try {
            Queue::fake();

            $request = Request::create('/api/callback/paprika', 'POST',
                [], [], [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$token}",
                    'HTTP_X_SIGNATURE' => $signature,
                    'HTTP_X_TIMESTAMP' => $timestamp,
                    'HTTP_X_PARTNER_ID' => $clientKey,
                    'CONTENT_TYPE' => 'application/json',
                ],
                $requestBody
            );

            $response = $this->service->callback($request);

            $this->assertEquals(200, $response->getStatusCode());
            $responseJson = json_decode($response->getContent(), true);
            $this->assertEquals('2002600', $responseJson['responseCode']);
        } finally {
            $order->forceDelete();
            $project->delete();
        }
    }
}
