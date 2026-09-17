<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQueueMonitorTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Queue Tester',
            'email' => 'admin-queue-'.uniqid().'@example.com',
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

    public function test_admin_can_get_queue_overview(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/admin/queue/overview');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonStructure([
            'data' => [
                'driver',
                'default_queue',
                'stats' => ['pending', 'scheduled', 'reserved', 'failed', 'total_in_queue'],
                'details',
                'timestamp',
            ],
        ]);
    }

    public function test_admin_can_get_active_jobs(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/admin/queue/active-jobs');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
    }

    public function test_admin_can_dispatch_test_queue_job(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/admin/queue/test-dispatch', [
            'message' => 'Automated test dispatch message',
            'delay_seconds' => 120,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.success', true);
    }

    public function test_admin_can_get_failed_jobs_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/admin/queue/failed-jobs');

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
    }

    public function test_unauthenticated_user_cannot_access_queue_monitor(): void
    {
        $response = $this->getJson('/api/admin/queue/overview');

        $response->assertStatus(401);
    }
}
