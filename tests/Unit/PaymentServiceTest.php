<?php

namespace Tests\Unit;

use App\Enums\PaymentModeType;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = new PaymentService();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;

        $this->gateway = PaymentGateway::create([
            'key' => 'paprika',
            'name' => 'Paprika Gateway',
            'description' => 'Paprika Unit Test',
        ]);

        $this->repo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_unit_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key' => 'test-api-key',
                'api_secret' => 'test-api-secret',
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
        Project::where('type', 'TEST')->delete();

        parent::tearDown();
    }

    private function fakePaprikaApis(): void
    {
        Http::fake([
            '*/api/snap/v1.0/access-token/b2b*' => Http::response([
                'accessToken' => 'fake-access-token',
                'tokenType' => 'Bearer',
                'expiresIn' => 3600,
            ], 200),

            '*/api/snap/v1.0/qr/qr-mpm-generate*' => Http::response([
                'responseCode' => '200',
                'responseMessage' => 'Success',
                'qrContent' => 'qr://fake-qr-code-test',
                'merchantId' => 'TEST-MERCHANT',
                'referenceNo' => 'TEST-PAPRIKA-001',
            ], 200),
        ]);
    }

    public function test_test_create_order_with_version_1(): void
    {
        $this->fakePaprikaApis();

        $result = $this->paymentService->testCreateOrder($this->repo, [
            'amount' => 15000,
            'currency' => 'IDR',
            'email' => 'buyer@example.com',
            'name' => 'Test Version 1',
            'version' => '1',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Paprika Gateway', $result['gateway']);
        $this->assertEquals('1', $result['version']);
        $this->assertEquals('qr://fake-qr-code-test', $result['checkout_url']);
    }

    public function test_test_create_order_with_version_2(): void
    {
        $this->fakePaprikaApis();
        Redis::shouldReceive('incr')->andReturn(1);
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('setex')->once()->andReturn(true);

        $result = $this->paymentService->testCreateOrder($this->repo, [
            'amount' => 20000,
            'currency' => 'IDR',
            'email' => 'buyer@example.com',
            'name' => 'Test Version 2',
            'version' => '2',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Paprika Gateway', $result['gateway']);
        $this->assertEquals('2', $result['version']);
        $this->assertStringContainsString('/detailpayment?token=', $result['checkout_url']);
    }
}
