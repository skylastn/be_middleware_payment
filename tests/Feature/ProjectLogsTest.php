<?php

namespace Tests\Feature;

use App\Enums\ProjectSlug;
use App\Enums\UserRole;
use App\Http\Helper\LogHelper;
use App\Model\Entity\Project;
use App\Model\Entity\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectLogsTest extends TestCase
{
    private User $admin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Logs Test',
            'email' => 'admin-logs-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
        ]);

        $this->project = Project::create([
            'name' => 'Logs Feature Test Project',
            'type' => 'LOGS_' . strtoupper(uniqid()),
            'slug' => ProjectSlug::DUITKU,
            'key' => 'logtestkey',
            'secure' => 'logtestsecure',
            'callback' => 'https://merchant.example.com/callback',
            'value' => 'logtesttoken',
        ]);

        (new \App\Repository\System\ProjectRepository())->ensureLogTableExists($this->project->id);

        // Insert sample logs using LogHelper
        LogHelper::sendLog('test_event_alpha', ['order_id' => 'ORD-101', 'amount' => 50000], (string) $this->project->id, 'key_alpha');
        LogHelper::sendLog('test_event_beta', ['order_id' => 'ORD-102', 'status' => 'PAID'], (string) $this->project->id, 'key_beta');
        LogHelper::sendErrorLog(new \Exception('Test simulate exception'), (string) $this->project->id, 'key_error');
    }

    protected function tearDown(): void
    {
        if (isset($this->project) && $this->project->exists) {
            Schema::dropIfExists('z__log__' . $this->project->id);
            Schema::dropIfExists('log__' . $this->project->id);
            $this->project->forceDelete();
        }

        if (isset($this->admin) && $this->admin->exists) {
            $this->admin->forceDelete();
        }

        parent::tearDown();
    }

    public function test_admin_can_retrieve_project_logs_with_standard_pagination(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/admin/projects/{$this->project->id}/logs");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('code', 200);
        $response->assertJsonPath('message', 'Success');
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
        $this->assertIsArray($response->json('data'));

        $firstItem = $response->json('data.0');
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('key', $firstItem);
        $this->assertArrayHasKey('value', $firstItem);
        $this->assertArrayHasKey('ip', $firstItem);
    }

    public function test_admin_can_retrieve_project_log_keys(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/admin/projects/{$this->project->id}/log-keys");

        $response->assertStatus(200);
        $response->assertJsonPath('status', true);
        $keys = $response->json('data');
        $this->assertContains('key_alpha', $keys);
        $this->assertContains('key_beta', $keys);
        $this->assertContains('key_error', $keys);
    }

    public function test_admin_can_filter_project_logs_by_key(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/admin/projects/{$this->project->id}/logs?key=key_alpha");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('key_alpha', $item['key']);
        }
    }

    public function test_admin_can_search_project_logs(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson("/api/admin/projects/{$this->project->id}/logs?search=ORD-101");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertIsArray($data[0]['value']);
        $this->assertEquals('ORD-101', $data[0]['value']['order_id']);
    }

    public function test_admin_can_clear_project_logs(): void
    {
        Sanctum::actingAs($this->admin);

        $deleteResponse = $this->deleteJson("/api/admin/projects/{$this->project->id}/logs");
        $deleteResponse->assertStatus(200);

        $fetchResponse = $this->getJson("/api/admin/projects/{$this->project->id}/logs");
        $fetchResponse->assertStatus(200);
        $this->assertEquals(0, $fetchResponse->json('total'));
        $this->assertEmpty($fetchResponse->json('data'));
    }

    public function test_unauthenticated_cannot_access_project_logs(): void
    {
        $response = $this->getJson("/api/admin/projects/{$this->project->id}/logs");
        $response->assertStatus(401);
    }
}
