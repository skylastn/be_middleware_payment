<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaprikaOrderCreateTest extends TestCase
{
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private Project $project;

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
            'key' => 'paprika_sandbox_order_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key' => 'test-client-key-order',
                'api_secret' => 'test-client-secret-order',
                'base_url' => 'https://sandbox.paprika.test',
                'private_key' => $this->generateTestPrivateKey(),
            ],
        ]);

        $this->project = Project::create([
            'name' => 'Paprika Order Test',
            'type' => 'AD',
            'slug' => 'paprika',
            'key' => 'orderkey1234',
            'secure' => 'ordersecure1234567890',
            'value' => Str::random(60),
            'callback' => 'https://example.com/callback',
        ]);

        Order::where('reference', 'like', 'AD-FM-%')->forceDelete();
    }

    protected function tearDown(): void
    {
        Order::where('reference', 'like', 'AD-FM-%')->forceDelete();

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

    private function generateTestPrivateKey(): string
    {
        $config = [
            'digest_alg' => 'SHA256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);
        return $privKey;
    }

    private function fakePaprikaApis(): void
    {
        Http::fake([
            '*/api/snap/v1.0/access-token/b2b*' => Http::response([
                'accessToken' => 'fake-access-token-12345',
                'tokenType' => 'Bearer',
                'expiresIn' => 3600,
            ], 200),

            '*/api/snap/v1.0/qr/qr-mpm-generate*' => Http::response([
                'responseCode' => '200',
                'responseMessage' => 'Success',
                'qrContent' => 'qr://fake-qr-content',
                'merchantId' => 'TEST-MERCHANT',
                'referenceNo' => 'AD-FM-TESTORDER',
            ], 200),
        ]);
    }

    public function test_order_create_requires_token(): void
    {
        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'FM-0000001',
        ]);

        $response->assertStatus(401);
    }

    public function test_order_create_invalid_token_returns_error(): void
    {
        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'FM-0000002',
        ], [
            'Token' => 'invalid-token-value',
        ]);

        $response->assertStatus(401);
    }

    public function test_order_create_with_paprika_project_success(): void
    {
        $this->fakePaprikaApis();

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'FM-0000003',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'code' => 200,
        ]);

        $this->assertDatabaseHas('orders', [
            'type' => 'AD',
            'reference' => 'AD-FM-0000003',
        ]);

        $order = Order::where('reference', 'AD-FM-0000003')->first();
        if ($order) {
            $order->forceDelete();
        }
    }

    public function test_order_create_paprika_returns_qr_link(): void
    {
        $this->fakePaprikaApis();

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 50000,
            'merchantOrderId' => 'FM-0000004',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('link', $data);
        $this->assertEquals('qr://fake-qr-content', $data['link']);
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Success Create Order Paprika', $data['message']);

        $order = Order::where('reference', 'AD-FM-0000004')->first();
        if ($order) {
            $order->forceDelete();
        }
    }

    public function test_order_create_paprika_saves_order_as_pending(): void
    {
        $this->fakePaprikaApis();

        $this->postJson('/api/order/create', [
            'paymentAmount' => 75000,
            'merchantOrderId' => 'FM-0000005',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $order = Order::where('reference', 'AD-FM-0000005')->first();
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());
        $this->assertEquals(PaymentModeType::sandbox, $order->getMode());
        $this->assertEquals($this->repo->id, $order->getPaymentRepositoryId());

        $order->forceDelete();
    }

    public function test_order_create_paprika_b2b_token_failure_returns_error(): void
    {
        Http::fake([
            '*/api/snap/v1.0/access-token/b2b*' => Http::response([
                'responseCode' => '500',
                'responseMessage' => 'Internal Server Error',
            ], 500),
        ]);

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'FM-0000006',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
        ]);
    }

    public function test_order_create_paprika_missing_payment_amount(): void
    {
        $response = $this->postJson('/api/order/create', [
            'merchantOrderId' => 'FM-0000007',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(400);
    }

    public function test_order_create_paprika_generates_unique_references(): void
    {
        $this->fakePaprikaApis();

        $this->postJson('/api/order/create', [
            'paymentAmount' => 10000,
            'merchantOrderId' => 'FM-UNIQUE-001',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $this->postJson('/api/order/create', [
            'paymentAmount' => 20000,
            'merchantOrderId' => 'FM-UNIQUE-002',
            'paymentMethod' => 'qris',
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $order1 = Order::where('reference', 'AD-FM-UNIQUE-001')->first();
        $order2 = Order::where('reference', 'AD-FM-UNIQUE-002')->first();

        $this->assertNotNull($order1);
        $this->assertNotNull($order2);
        $this->assertNotEquals($order1->getId(), $order2->getId());

        if ($order1) {
            $order1->forceDelete();
        }
        if ($order2) {
            $order2->forceDelete();
        }
    }
}
