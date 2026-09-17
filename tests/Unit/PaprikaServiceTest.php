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
                'api_key_paprika' => 'test-client-key-12345',
                'api_secret_paprika' => 'test-client-secret-67890',
                'api_client' => 'test-client-key-12345',
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

            $request = Request::create('/api/paprika/callback', 'POST',
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

        $stringToSign = "POST:/api/paprika/callback:{$token}:{$bodyHash}:{$timestamp}";
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

            $request = Request::create('/api/paprika/callback', 'POST',
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

    public function test_new_paprika_credential_structure_flow(): void
    {
        $repoRepo = new \App\Repository\Payment\PaymentRepositoryRepository();

        $apiKeyPaprika = 'test_key_paprika_abc123';
        $apiSecretPaprika = 'test_secret_paprika_xyz789';
        $apiClient = 'test_client_key_snap_custom';
        $apiSecret = 'test_secret_snap_custom';

        $newRepo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_new_config_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key_paprika' => $apiKeyPaprika,
                'api_secret_paprika' => $apiSecretPaprika,
                'api_client' => $apiClient,
                'api_secret' => $apiSecret,
                'base_url' => 'https://staging-gateway.paprika.co.id',
                'private_key' => $this->privateKeyPem,
            ],
        ]);

        try {
            // 1. Check findByGatewayAndClientKey can find repo by api_client
            $foundByClient = $repoRepo->findByGatewayAndClientKey($this->gateway->id, $apiClient);
            $this->assertNotNull($foundByClient);
            $this->assertEquals($newRepo->id, $foundByClient->id);

            // 2. Check findByGatewayAndClientKey can find repo by api_key_paprika
            $foundByApiKey = $repoRepo->findByGatewayAndClientKey($this->gateway->id, $apiKeyPaprika);
            $this->assertNotNull($foundByApiKey);
            $this->assertEquals($newRepo->id, $foundByApiKey->id);

            // 3. Check generateSignature uses api_key_paprika
            $asymSig = $this->service->generateSignature($newRepo, '2026-09-15T12:00:00+00:00');
            $this->assertEquals($apiKeyPaprika, $asymSig['X-CLIENT-KEY']);
            $this->assertNotEmpty($asymSig['X-SIGNATURE']);

            // 4. Check signBySymmetricSignature uses api_key_paprika and api_secret_paprika
            $symSig = $this->service->signBySymmetricSignature(
                $newRepo,
                'test-access-token',
                'POST',
                '/api/snap/v1.0/qr/qr-mpm-generate',
                '{"partnerReferenceNo":"REF123"}',
                '2026-09-15T12:00:00+00:00'
            );
            $this->assertEquals($apiKeyPaprika, $symSig['X-CLIENT-KEY']);

            // Expected signature generated specifically with api_secret_paprika
            $bodyHash = hash('sha256', '{"partnerReferenceNo":"REF123"}');
            $expectedStringToSign = "POST:/api/snap/v1.0/qr/qr-mpm-generate:test-access-token:{$bodyHash}:2026-09-15T12:00:00+00:00";
            $expectedSignature = base64_encode(hash_hmac('sha512', $expectedStringToSign, $apiSecretPaprika, true));
            $this->assertEquals($expectedSignature, $symSig['X-SIGNATURE']);

            // 5. Callback signature uses api_secret and verifies X-PARTNER-ID against api_client
            $cbBody = [
                'originalPartnerReferenceNo' => 'FM-0000099',
                'originalReferenceNo' => 'AD-FM-0000099',
                'latestTransactionStatus' => '00',
            ];
            $cbJson = json_encode($cbBody);
            $cbBodyHash = hash('sha256', $cbJson);
            $cbToken = 'test-new-cb-token';
            $redis = new \App\Services\System\RedisService;
            $redis->storeSnapAccessToken($cbToken, $apiClient, 900);

            $cbTimestamp = '2026-09-15T12:00:00+00:00';
            $cbStringToSign = "POST:/api/paprika/callback:{$cbToken}:{$cbBodyHash}:{$cbTimestamp}";
            // Signed with api_secret
            $cbSignature = base64_encode(hash_hmac('sha512', $cbStringToSign, $apiSecret, true));

            $order = Order::create([
                'id' => date('Ymd') . '-00999',
                'type' => 'AD',
                'reference' => 'AD-FM-0000099',
                'status' => OrderStatus::PENDING,
                'mode' => PaymentModeType::sandbox,
                'payment_repository_id' => $newRepo->id,
                'request' => '{}',
            ]);

            $project = Project::create([
                'name' => 'New Test Project',
                'type' => 'AD',
                'slug' => 'paprika',
                'key' => 'testkey999',
                'secure' => 'testsecure999',
                'value' => Str::random(60),
                'callback' => 'https://example.com/callback',
            ]);

            Queue::fake();

            $cbRequest = Request::create('/api/paprika/callback', 'POST',
                [], [], [],
                [
                    'HTTP_AUTHORIZATION' => "Bearer {$cbToken}",
                    'HTTP_X_SIGNATURE' => $cbSignature,
                    'HTTP_X_TIMESTAMP' => $cbTimestamp,
                    'HTTP_X_PARTNER_ID' => $apiClient,
                    'CONTENT_TYPE' => 'application/json',
                ],
                $cbJson
            );

            $cbResponse = $this->service->callback($cbRequest);
            $this->assertEquals(200, $cbResponse->getStatusCode());
            $cbResponseJson = json_decode($cbResponse->getContent(), true);
            $this->assertEquals('2002600', $cbResponseJson['responseCode']);

            $order->refresh();
            $this->assertEquals(OrderStatus::SUCCESS, $order->getStatus());

            $order->forceDelete();
            $project->delete();
        } finally {
            $newRepo->forceDelete();
        }
    }

    public function test_paprika_service_supports_private_key_and_public_key_paprika(): void
    {
        $repo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_keys_test_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key_paprika' => 'test-api-key-paprika',
                'api_secret_paprika' => 'test-api-secret-paprika',
                'api_client' => 'test-api-client-snap',
                'api_secret' => 'test-api-secret-snap',
                'base_url' => 'https://staging-gateway.paprika.co.id',
                'private_key' => $this->privateKeyPem,
                'public_key_paprika' => $this->publicKeyPem,
            ],
        ]);

        try {
            // 1. generateSignature should read private_key_paprika
            $sigData = $this->service->generateSignature($repo, '2026-09-17T00:00:00+00:00');
            $this->assertNotEmpty($sigData['X-SIGNATURE']);
            $this->assertEquals('test-api-key-paprika', $sigData['X-CLIENT-KEY']);

            // 2. generateB2BAccessToken should read public_key_paprika
            $clientKey = 'test-api-client-snap';
            $timestamp = now()->toIso8601String();
            $stringToSign = "{$clientKey}|{$timestamp}";
            $privKey = openssl_pkey_get_private($this->privateKeyPem);
            openssl_sign($stringToSign, $rawSig, $privKey, OPENSSL_ALGO_SHA256);

            $request = Request::create(
                '/api/paprika/snap/v1.0/access-token/b2b',
                'POST',
                ['grantType' => 'client_credentials'],
                [],
                [],
                [
                    'HTTP_X_CLIENT_KEY' => $clientKey,
                    'HTTP_X_TIMESTAMP' => $timestamp,
                    'HTTP_X_SIGNATURE' => base64_encode($rawSig),
                    'CONTENT_TYPE' => 'application/json',
                ],
                json_encode(['grantType' => 'client_credentials'])
            );

            $response = $this->service->generateB2BAccessToken($request);
            $this->assertEquals(200, $response->getStatusCode());
            $json = json_decode($response->getContent(), true);
            $this->assertEquals('2007300', $json['responseCode']);
            $this->assertNotEmpty($json['accessToken']);
        } finally {
            $repo->forceDelete();
        }
    }
}
