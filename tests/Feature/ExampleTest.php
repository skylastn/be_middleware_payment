<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
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
        $response->assertSee('backoffice-root');
    }

    public function test_admin_can_login_with_api_token(): void
    {
        $email = 'token-admin-'.uniqid().'@example.com';
        $admin = User::query()->create([
            'name' => 'Token Admin',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);

        try {
            $this->postJson('/api/admin/login', [
                'email' => $email,
                'password' => 'password',
            ])
                ->assertStatus(200)
                ->assertJsonStructure(['token', 'user' => ['name', 'email']]);
        } finally {
            $admin->tokens()->delete();
            $admin->delete();
        }
    }

    public function test_admin_can_view_dashboard(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('backoffice-root');
    }

    public function test_admin_can_load_dashboard_data_api(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'orders',
                    'paidOrders',
                    'pendingOrders',
                    'failedOrders',
                    'projects',
                    'paymentGateways',
                    'paymentRepositories',
                    'paymentMethods',
                ],
                'statusCounts',
                'statusMix' => ['success', 'pending', 'failedExpired', 'successDeg', 'pendingDeg'],
                'modeCounts',
                'recentOrders',
                'projects',
                'repositories',
                'updatedAt',
            ]);
    }

    public function test_only_admin_can_view_log_viewer(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $user = new User([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'role' => UserRole::USER,
        ]);
        $user->id = 2;

        $this->actingAs($admin)
            ->get('/log-viewer')
            ->assertStatus(200);

        $this->actingAs($user)
            ->get('/log-viewer')
            ->assertForbidden();
    }

    public function test_admin_can_view_resource_tabs(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $this->get('/admin/orders')
            ->assertStatus(200)
            ->assertSee('backoffice-root')
            ->assertDontSee('Create Order');

        $this->get('/admin/projects/create')
            ->assertStatus(200)
            ->assertSee('backoffice-root')
            ->assertDontSee('data-readonly-field="key"', false)
            ->assertDontSee('data-readonly-field="secure"', false)
            ->assertDontSee('data-readonly-field="value"', false);
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
            $this->get('/admin/projects/'.$project->getAttribute($project->getKeyName()).'/edit')
                ->assertStatus(200)
                ->assertSee('backoffice-root');
        }

        $gateway = PaymentGateway::query()->first();
        if ($gateway) {
            $this->get('/admin/payment-gateways/'.$gateway->getAttribute($gateway->getKeyName()).'/edit')
                ->assertStatus(200)
                ->assertSee('backoffice-root');
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
            Sanctum::actingAs($admin);

            $this->postJson('/api/project/create', [
                    'name' => 'Generated Credential Test',
                    'type' => $type,
                    'slug' => 'duitku',
                    'callback' => 'https://example.com/callback',
                ])
                ->assertStatus(200);

            $project = Project::query()->where('type', $type)->firstOrFail();

            $this->assertSame(10, strlen($project->key));
            $this->assertSame(20, strlen($project->secure));
            $this->assertSame(60, strlen($project->value));
            $this->assertTrue(Schema::hasTable('log__'.$project->id));
        } finally {
            $project = Project::query()->where('type', $type)->first();
            if ($project) {
                Schema::dropIfExists('log__'.$project->id);
                $project->delete();
            }
        }
    }

    public function test_admin_can_sync_missing_project_log_tables(): void
    {
        $admin = new User([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::ADMIN,
        ]);
        $admin->id = 1;

        $project = Project::query()->create([
            'name' => 'Missing Log Test',
            'type' => 'LOG'.uniqid(),
            'slug' => 'duitku',
            'callback' => 'https://example.com/callback',
            'key' => 'logkey',
            'secure' => 'logsecurevalue',
            'value' => 'logtokenvalue',
        ]);

        try {
            Schema::dropIfExists('log__'.$project->id);
            $this->assertFalse(Schema::hasTable('log__'.$project->id));

            Sanctum::actingAs($admin);

            $response = $this->postJson('/api/project/sync-missing-log')
                ->assertStatus(200)
                ->assertJsonPath('status', true);

            $this->assertTrue(Schema::hasTable('log__'.$project->id));
            $this->assertContains('log__'.$project->id, $response->json('data.tables'));
        } finally {
            Schema::dropIfExists('log__'.$project->id);
            $project->delete();
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

            $this->get('/admin/projects/'.$projectId.'/edit')
                ->assertStatus(200)
                ->assertSee('backoffice-root');

            Sanctum::actingAs($admin);

            $this->getJson('/api/project/'.$projectId)
                ->assertStatus(200)
                ->assertSee('originalkey')
                ->assertSee('originalsecurevalue')
                ->assertSee('originaltokenvalue');

            $this->putJson('/api/project/'.$projectId, [
                    'name' => 'Credential Lock Test Updated',
                    'type' => $project->type,
                    'slug' => 'xendit',
                    'callback' => 'https://example.com/updated-callback',
                    'key' => 'changedkey',
                    'secure' => 'changedsecure',
                    'value' => 'changedvalue',
                ])
                ->assertStatus(200);

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

        $this->get('/admin/orders/'.$orderId)
            ->assertStatus(200)
            ->assertSee('backoffice-root');

        $this->get('/admin/orders/'.$orderId.'/edit')
            ->assertStatus(200)
            ->assertSee('backoffice-root');

        Sanctum::actingAs($admin);

        $this->putJson('/api/order/'.$orderId, [])
            ->assertStatus(405);
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

        Sanctum::actingAs($admin);

        $this->postJson('/api/order/'.$order->getAttribute($order->getKeyName()).'/resend-callback')
            ->assertStatus(200);

        Http::assertSentCount(1);
    }
}
