<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaprikaVATest extends TestCase
{
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private Project $project;
    private array $createdMethodIds = [];

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
            'key' => 'paprika_sandbox_va_' . uniqid(),
            'mode' => PaymentModeType::sandbox->value,
            'value' => [
                'api_key' => 'test-client-key-va',
                'api_secret' => 'test-client-secret-va',
                'base_url' => 'https://sandbox.paprika.test',
                'private_key' => $this->generateTestPrivateKey(),
            ],
        ]);

        $this->project = Project::create([
            'name' => 'Paprika VA Test',
            'type' => 'AD',
            'slug' => 'paprika',
            'key' => 'vakey1234',
            'secure' => 'vasecure1234567890',
            'value' => Str::random(60),
            'callback' => 'https://example.com/callback',
        ]);

        Order::where('reference', 'like', 'AD-VA-%')->forceDelete();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMethodIds as $id) {
            PaymentMethod::find($id)?->forceDelete();
        }

        Order::where('reference', 'like', 'AD-VA-%')->forceDelete();

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

    private function fakeVAApis(): void
    {
        Http::fake([
            '*/api/snap/v1.0/access-token/b2b*' => Http::response([
                'accessToken' => 'fake-access-token-va',
                'tokenType' => 'Bearer',
                'expiresIn' => 3600,
            ], 200),

            '*/api/snap/v1.0/transfer-va/create-va*' => Http::response([
                'responseCode' => '200',
                'responseMessage' => 'Success',
                'virtualAccountData' => [
                    'virtualAccountNo' => '8199999999999999',
                ],
                'trxId' => 'AD-VA-0000001',
            ], 200),
        ]);
    }

    private function createPaprikaMethod(string $key, ?string $bankCode): PaymentMethod
    {
        $method = PaymentMethod::create([
            'key' => $key,
            'from' => 'paprika',
            'bankCode' => $bankCode,
            'name' => $key,
        ]);
        $this->createdMethodIds[] = $method->id;

        return $method;
    }

    public function test_va_create_uses_db_bank_code(): void
    {
        $this->createPaprikaMethod('VA_PERMATA', '013');

        $this->fakeVAApis();

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 250000,
            'merchantOrderId' => 'VA-0000001',
            'paymentMethod' => 'VA_PERMATA',
            'paymentRepositoryId' => $this->repo->id,
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'code' => 200,
        ]);

        $data = $response->json('data');
        $this->assertEquals('8199999999999999', $data['link']);
        $this->assertEquals('Success Create Order Paprika VA', $data['message']);

        Http::assertSent(function ($request) {
            if (! Str::contains($request->url(), 'transfer-va/create-va')) {
                return false;
            }

            $body = $request->data();
            $this->assertEquals('013', $body['additionalInfo']['bankCode']);

            return true;
        });

        $order = Order::where('reference', 'AD-VA-0000001')->first();
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());
        $this->assertEquals($this->repo->id, $order->getPaymentRepositoryId());
    }

    public function test_va_payment_method_is_case_insensitive(): void
    {
        $this->createPaprikaMethod('VA_MAYBANK', '016');

        $this->fakeVAApis();

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'VA-0000002',
            'paymentMethod' => 'va_maybank',
            'paymentRepositoryId' => $this->repo->id,
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            if (! Str::contains($request->url(), 'transfer-va/create-va')) {
                return false;
            }

            $this->assertEquals('016', $request->data()['additionalInfo']['bankCode']);

            return true;
        });
    }

    public function test_unsupported_va_payment_method_returns_error(): void
    {
        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'VA-0000003',
            'paymentMethod' => 'VA_BCA',
            'paymentRepositoryId' => $this->repo->id,
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
        ]);
        $this->assertStringContainsString('no supported payment method VA_BCA', $response->json('message'));
    }

    public function test_va_without_bank_code_is_not_routed(): void
    {
        $this->createPaprikaMethod('VA_DANAMON', null);

        $response = $this->postJson('/api/order/create', [
            'paymentAmount' => 100000,
            'merchantOrderId' => 'VA-0000004',
            'paymentMethod' => 'VA_DANAMON',
            'paymentRepositoryId' => $this->repo->id,
            'mode' => 'sandbox',
        ], [
            'Token' => $this->project->value,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => false,
        ]);
        $this->assertStringContainsString('no supported payment method VA_DANAMON', $response->json('message'));
    }
}