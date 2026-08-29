<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\PaymentCategory;
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

        // Verify PaymentMethod has category_id and category populated
        $dqMethod = PaymentMethod::where('key', 'DQ')->first();
        $this->assertNotNull($dqMethod);
        $this->assertEquals($qrisCategory->id, $dqMethod->category_id);
        $this->assertNotNull($dqMethod->category);
        $this->assertEquals('qris', $dqMethod->category->key);
        $this->assertEquals('QRIS', $dqMethod->category->title);

        // Verify VA method
        $bcMethod = PaymentMethod::where('key', 'BC')->first();
        $this->assertNotNull($bcMethod);
        $this->assertEquals($vaCategory->id, $bcMethod->category_id);
        $this->assertNotNull($bcMethod->category);
        $this->assertEquals('va', $bcMethod->category->key);
        $this->assertEquals('Virtual Account', $bcMethod->category->title);
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
    }

    public function test_admin_can_create_payment_method_with_category_id(): void
    {
        $this->seed(PaymentCategorySeeder::class);

        $vaCategory = PaymentCategory::where('key', 'va')->firstOrFail();

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/payment-methods/create', [
            'key' => 'TEST_VA',
            'name' => 'Test Bank VA',
            'category_id' => $vaCategory->id,
            'from' => 'duitku',
            'bankCode' => 'test',
        ]);

        $response->assertStatus(200);
        $this->assertEquals($vaCategory->id, $response->json('data.category_id'));
        $this->assertEquals('Virtual Account', $response->json('data.category.title'));

        PaymentMethod::where('key', 'TEST_VA')->forceDelete();
    }

    public function test_admin_can_create_and_update_payment_method_with_image(): void
    {
        $this->seed(PaymentCategorySeeder::class);
        $qrisCategory = PaymentCategory::where('key', 'qris')->firstOrFail();

        Sanctum::actingAs($this->admin);

        // 1. Create with direct image URL
        $createResponse = $this->postJson('/api/admin/payment-methods/create', [
            'key' => 'TEST_IMG_METHOD',
            'name' => 'Test Image Method',
            'category_id' => $qrisCategory->id,
            'from' => 'duitku',
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
            'from' => 'duitku',
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
            'from' => 'duitku',
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

        $response = $this->getJson('/api/payment/getDetailPaymentMethod?key=BC&from=duitku');
        $response->assertStatus(200);
        $response->assertJsonPath('data.key', 'BC');
        $response->assertJsonPath('data.name', 'BCA VA');
    }
}
