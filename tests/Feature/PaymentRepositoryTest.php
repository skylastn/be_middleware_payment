<?php

namespace Tests\Feature;

use App\Enums\PaymentModeType;
use App\Enums\UserRole;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentRepositoryTest extends TestCase
{
    private User $admin;
    private PaymentGateway $gateway;
    private PaymentRepository $repo;
    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyPair, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;

        $this->gateway = PaymentGateway::create([
            'key' => 'paprika',
            'name' => 'Paprika Feature',
            'description' => 'Paprika Feature Test',
        ]);

        $this->repo = PaymentRepository::create([
            'payment_gateway_id' => $this->gateway->id,
            'key' => 'paprika_feat_' . uniqid(),
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
        if ($this->admin && $this->admin->exists) {
            $this->admin->forceDelete();
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
                'qrContent' => 'qr://fake-qr-code-feature',
                'merchantId' => 'TEST-MERCHANT',
                'referenceNo' => 'TEST-PAPRIKA-FEAT',
            ], 200),
        ]);
    }

    public function test_admin_can_execute_test_order_with_version(): void
    {
        $this->fakePaprikaApis();
        Redis::shouldReceive('incr')->andReturn(1);
        Redis::shouldReceive('expire')->andReturn(true);
        Redis::shouldReceive('setex')->once()->andReturn(true);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/admin/payment-repositories/{$this->repo->id}/test-order", [
            'amount' => 50000,
            'currency' => 'IDR',
            'email' => 'admin-tester@example.com',
            'name' => 'Admin Tester',
            'version' => '2',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.success', true);
        $response->assertJsonPath('data.version', '2');
        $this->assertStringContainsString('/home?token=', $response->json('data.checkout_url'));
    }
}
