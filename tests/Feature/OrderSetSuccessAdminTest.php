<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Enums\UserRole;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderSetSuccessAdminTest extends TestCase
{
    private Project $project;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::firstOrCreate(
            ['type' => 'SSA'],
            [
                'name' => 'Set Success Admin Test',
                'slug' => 'duitku',
                'key' => 'ssakey123',
                'secure' => 'ssasecure123',
                'value' => 'ssa_token_' . Str::random(32),
                'callback' => 'https://example.com/callback',
            ]
        );

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@example.com'],
            [
                'name' => 'Admin Test',
                'password' => bcrypt('password'),
                'role' => UserRole::ADMIN,
            ]
        );
    }

    protected function tearDown(): void
    {
        Order::where('type', 'SSA')->delete();
        parent::tearDown();
    }

    public function test_admin_can_set_order_success_and_dispatch_callbacks(): void
    {
        Queue::fake([SendMerchantCallback::class, SendNotificationJob::class]);

        $order = Order::create([
            'id' => 'ORDER-SSA-001',
            'type' => 'SSA',
            'reference' => 'SSA-TEST-001',
            'payment_method' => 'BC',
            'value' => '1234567890',
            'status' => OrderStatus::PENDING->value,
            'mode' => PaymentModeType::sandbox->value,
            'amount' => 10000,
        ]);

        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/admin/orders/{$order->getId()}/set-success");

        $response->assertStatus(200);

        $fresh = Order::find('ORDER-SSA-001');
        $this->assertNotNull($fresh);
        $this->assertEquals(OrderStatus::SUCCESS, $fresh->getStatus());

        Queue::assertPushed(SendMerchantCallback::class);
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_admin_set_success_returns_404_for_unknown_order(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/admin/orders/NONEXISTENT-999/set-success');

        $response->assertStatus(400)
            ->assertJson([
                'status' => false,
                'code' => 404,
            ]);
    }
}
