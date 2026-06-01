<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\User;
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
}
