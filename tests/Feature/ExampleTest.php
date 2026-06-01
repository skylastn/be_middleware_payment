<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Http;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Payment Monitoring');
    }

    public function test_admin_can_view_resource_tabs(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertStatus(200)
            ->assertSee('Orders')
            ->assertSee('View')
            ->assertDontSee('Create Order')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');

        $this->actingAs($admin)
            ->get('/admin/projects/create')
            ->assertStatus(200)
            ->assertSee('Create Project');
    }

    public function test_admin_edit_links_use_primary_keys(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $project = Project::query()->first();
        if ($project) {
            $this->actingAs($admin)
                ->get('/admin/projects/'.$project->getAttribute($project->getKeyName()).'/edit')
                ->assertStatus(200)
                ->assertSee('Edit Project');
        }

        $gateway = PaymentGateway::query()->first();
        if ($gateway) {
            $this->actingAs($admin)
                ->get('/admin/payment-gateways/'.$gateway->getAttribute($gateway->getKeyName()).'/edit')
                ->assertStatus(200)
                ->assertSee('Edit Payment Gateway');
        }
    }

    public function test_admin_can_only_view_orders_from_resource_page(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $order = Order::query()->first();
        if (! $order) {
            $this->markTestSkipped('No order is available.');
        }

        $orderId = $order->getAttribute($order->getKeyName());

        $this->actingAs($admin)
            ->get('/admin/orders/'.$orderId)
            ->assertStatus(200)
            ->assertSee('View Order');

        $this->actingAs($admin)
            ->get('/admin/orders/'.$orderId.'/edit')
            ->assertForbidden();
    }

    public function test_admin_can_resend_success_order_callback(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $order = Order::query()
            ->where('status', 'SUCCESS')
            ->whereHas('project')
            ->first();

        if (! $order) {
            $this->markTestSkipped('No successful order with project is available.');
        }

        Http::fake([
            '*' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->getAttribute($order->getKeyName()).'/resend-callback')
            ->assertRedirect();

        Http::assertSentCount(1);
    }
}
