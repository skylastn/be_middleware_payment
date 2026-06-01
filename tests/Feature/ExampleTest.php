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
            ->assertSee('Create Project')
            ->assertDontSee('Key')
            ->assertDontSee('Secure')
            ->assertDontSee('Value');
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

    public function test_admin_project_create_generates_credentials(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $type = 'TEST'.uniqid();

        try {
            $this->actingAs($admin)
                ->post('/admin/projects', [
                    'name' => 'Generated Credential Test',
                    'type' => $type,
                    'slug' => 'duitku',
                    'callback' => 'https://example.com/callback',
                ])
                ->assertRedirect('/admin/projects');

            $project = Project::query()->where('type', $type)->firstOrFail();

            $this->assertSame(10, strlen($project->key));
            $this->assertSame(20, strlen($project->secure));
            $this->assertSame(60, strlen($project->value));
        } finally {
            Project::query()->where('type', $type)->delete();
        }
    }

    public function test_admin_project_credentials_are_not_editable_from_ui(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $project = Project::query()->create([
            'name' => 'Credential Lock Test',
            'type' => 'LOCK'.uniqid(),
            'slug' => 'duitku',
            'callback' => 'https://example.com/callback',
            'key' => 'originalkey',
            'secure' => 'originalsecurevalue',
            'value' => 'originaltokenvalue',
        ]);

        try {
            $projectId = $project->getAttribute($project->getKeyName());

            $this->actingAs($admin)
                ->get('/admin/projects/'.$projectId.'/edit')
                ->assertStatus(200)
                ->assertSee('Key')
                ->assertSee('Secure')
                ->assertSee('Value')
                ->assertSee('originalkey')
                ->assertSee('originalsecurevalue')
                ->assertSee('originaltokenvalue')
                ->assertSee('readonly', false);

            $this->actingAs($admin)
                ->put('/admin/projects/'.$projectId, [
                    'name' => 'Credential Lock Test Updated',
                    'type' => $project->type,
                    'slug' => 'xendit',
                    'callback' => 'https://example.com/updated-callback',
                    'key' => 'changedkey',
                    'secure' => 'changedsecure',
                    'value' => 'changedvalue',
                ])
                ->assertRedirect('/admin/projects');

            $project->refresh();

            $this->assertSame('originalkey', $project->key);
            $this->assertSame('originalsecurevalue', $project->secure);
            $this->assertSame('originaltokenvalue', $project->value);
        } finally {
            $project->delete();
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
