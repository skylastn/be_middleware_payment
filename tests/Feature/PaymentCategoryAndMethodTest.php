<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\User;
use Database\Seeders\PaymentCategorySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCategoryAndMethodTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Seeder Test',
            'email' => 'admin-seeder-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->admin && $this->admin->exists) {
            $this->admin->forceDelete();
        }

        parent::tearDown();
    }

    public function test_payment_category_and_method_seeders(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        $vaCategory = PaymentCategory::where('key', 'va')->first();
        $ccCategory = PaymentCategory::where('key', 'cc')->first();
        $qrisCategory = PaymentCategory::where('key', 'qris')->first();

        $this->assertNotNull($vaCategory);
        $this->assertEquals('Virtual Account', $vaCategory->title);

        $this->assertNotNull($ccCategory);
        $this->assertEquals('Credit Card', $ccCategory->title);

        $this->assertNotNull($qrisCategory);
        $this->assertEquals('QRIS', $qrisCategory->title);

        // Verify PaymentMethod has category_id, payment_gateway_id and relationships populated
        $dqMethod = PaymentMethod::where('key', 'DQ')->first();
        $this->assertNotNull($dqMethod);
        $this->assertEquals($qrisCategory->id, $dqMethod->category_id);
        $this->assertNotNull($dqMethod->category);
        $this->assertEquals('qris', $dqMethod->category->key);
        $this->assertEquals('QRIS', $dqMethod->category->title);
        $this->assertNotNull($dqMethod->payment_gateway_id);
        $this->assertNotNull($dqMethod->payment_gateway);
        $this->assertEquals('duitku', $dqMethod->payment_gateway->key);

        // Verify VA method
        $bcMethod = PaymentMethod::where('key', 'BC')->first();
        $this->assertNotNull($bcMethod);
        $this->assertEquals($vaCategory->id, $bcMethod->category_id);
        $this->assertNotNull($bcMethod->category);
        $this->assertEquals('va', $bcMethod->category->key);
        $this->assertEquals('Virtual Account', $bcMethod->category->title);
        $this->assertNotNull($bcMethod->payment_gateway_id);
        $this->assertEquals('duitku', $bcMethod->payment_gateway->key);
    }

    public function test_admin_api_returns_payment_method_with_category(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/payment-methods?per_page=50');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $dq = collect($data)->firstWhere('key', 'DQ');
        $this->assertNotNull($dq);
        $this->assertNotNull($dq['category_id']);
        $this->assertNotNull($dq['category']);
        $this->assertEquals('qris', $dq['category']['key']);
        $this->assertEquals('QRIS', $dq['category']['title']);
        $this->assertNotNull($dq['payment_gateway_id']);
        $this->assertNotNull($dq['gateway']);
        $this->assertEquals('duitku', $dq['gateway']['key']);
    }

    public function test_admin_can_create_payment_method_with_category_id(): void
    {
        $this->seed(PaymentCategorySeeder::class);

        $vaCategory = PaymentCategory::where('key', 'va')->firstOrFail();
        $duitkuGateway = PaymentGateway::where('key', 'duitku')->firstOrFail();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/payment-methods/create', [
            'key' => 'TEST_VA',
            'name' => 'Test Bank VA',
            'category_id' => $vaCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'bankCode' => 'test',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($vaCategory->id, $response->json('data.category_id'));
        $this->assertEquals('Virtual Account', $response->json('data.category.title'));

        PaymentMethod::where('key', 'TEST_VA')->forceDelete();
    }

    public function test_admin_can_create_payment_method_with_payment_gateway_id(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        $vaCategory = PaymentCategory::where('key', 'va')->firstOrFail();
        $stripeGateway = PaymentGateway::where('key', 'stripe')->firstOrFail();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/payment-methods/create', [
            'key' => 'TEST_STRIPE_METHOD',
            'name' => 'Test Stripe Channel',
            'category_id' => $vaCategory->id,
            'payment_gateway_id' => $stripeGateway->id,
            'bankCode' => 'stripe_card',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($stripeGateway->id, $response->json('data.payment_gateway_id'));
        $this->assertEquals('Stripe', $response->json('data.gateway.name'));

        $created = PaymentMethod::where('key', 'TEST_STRIPE_METHOD')->first();
        $this->assertNotNull($created);
        $this->assertEquals($stripeGateway->id, $created->getPaymentGatewayId());

        $created->forceDelete();
    }

    public function test_admin_can_create_and_update_payment_method_with_image(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $qrisCategory = PaymentCategory::where('key', 'qris')->firstOrFail();
        $duitkuGateway = PaymentGateway::where('key', 'duitku')->firstOrFail();

        Sanctum::actingAs($this->admin);

        // 1. Create with direct image URL
        $createResponse = $this->postJson('/api/admin/payment-methods/create', [
            'key' => 'TEST_IMG_METHOD',
            'name' => 'Test Image Method',
            'category_id' => $qrisCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'bankCode' => '',
            'image' => 'https://example.com/images/qris-logo.png',
        ]);

        $createResponse->assertStatus(200);
        $this->assertEquals('https://example.com/images/qris-logo.png', $createResponse->json('data.image'));
        $createdId = $createResponse->json('data.id');

        // 2. Update with new image URL
        $updateResponse = $this->putJson("/api/admin/payment-methods/{$createdId}", [
            'key' => 'TEST_IMG_METHOD',
            'name' => 'Test Image Method Updated',
            'category_id' => $qrisCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'bankCode' => '',
            'image' => 'https://example.com/images/qris-new-logo.png',
        ]);

        $updateResponse->assertStatus(200);
        $this->assertEquals('https://example.com/images/qris-new-logo.png', $updateResponse->json('data.image'));

        // 3. Update with base64 data URL
        $dummyPngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $base64Response = $this->putJson("/api/admin/payment-methods/{$createdId}", [
            'key' => 'TEST_IMG_METHOD',
            'name' => 'Test Image Method Updated Base64',
            'category_id' => $qrisCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'bankCode' => '',
            'image' => $dummyPngBase64,
        ]);

        $base64Response->assertStatus(200);
        $storedImagePath = $base64Response->json('data.image');
        $storedImageUrl = $base64Response->json('data.image_url');
        $this->assertStringStartsWith('payment-methods/pm_', $storedImagePath);
        $this->assertStringContainsString('/storage/payment-methods/pm_', $storedImageUrl);

        PaymentMethod::where('key', 'TEST_IMG_METHOD')->forceDelete();
    }

    public function test_public_api_get_detail_payment_method_by_key(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
        $duitkuGateway = PaymentGateway::where('key', 'duitku')->firstOrFail();

        $response = $this->getJson("/api/payment/getDetailPaymentMethod?key=BC&payment_gateway_id={$duitkuGateway->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.key', 'BC');
        $response->assertJsonPath('data.name', 'BCA VA');
    }

    public function test_client_payment_method_api_hides_inactive_methods(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);

        $vaCategory = PaymentCategory::where('key', 'va')->firstOrFail();
        $duitkuGateway = PaymentGateway::where('key', 'duitku')->firstOrFail();

        $activeMethod = PaymentMethod::create([
            'key' => 'TEST_ACTIVE_METHOD',
            'name' => 'Active Test Method',
            'category_id' => $vaCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'is_active' => true,
        ]);

        $inactiveMethod = PaymentMethod::create([
            'key' => 'TEST_INACTIVE_METHOD',
            'name' => 'Inactive Test Method',
            'category_id' => $vaCategory->id,
            'payment_gateway_id' => $duitkuGateway->id,
            'is_active' => false,
        ]);

        // Public client API list only returns active
        $clientResponse = $this->getJson('/api/payment/getPaymentMethod');
        $clientResponse->assertStatus(200);
        $clientKeys = collect($clientResponse->json('data'))->pluck('key')->all();
        $this->assertContains('TEST_ACTIVE_METHOD', $clientKeys);
        $this->assertNotContains('TEST_INACTIVE_METHOD', $clientKeys);

        // Public client API detail hides inactive
        $clientDetail = $this->getJson('/api/payment/getDetailPaymentMethod?key=TEST_INACTIVE_METHOD');
        $clientDetail->assertStatus(200);
        $this->assertNull($clientDetail->json('data'));

        // Admin API can see inactive
        Sanctum::actingAs($this->admin);
        $adminResponse = $this->getJson('/api/admin/payment-methods?per_page=100');
        $adminResponse->assertStatus(200);
        $adminKeys = collect($adminResponse->json('data'))->pluck('key')->all();
        $this->assertContains('TEST_ACTIVE_METHOD', $adminKeys);
        $this->assertContains('TEST_INACTIVE_METHOD', $adminKeys);

        $activeMethod->forceDelete();
        $inactiveMethod->forceDelete();
    }
}
