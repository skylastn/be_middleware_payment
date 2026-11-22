<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Model\Entity\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSessionLifetimeTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'session_ttl_test@example.com'],
            [
                'name' => 'Session TTL Test',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN,
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->admin->tokens()->delete();
        parent::tearDown();
    }

    public function test_login_issues_token_with_expiry(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'session_ttl_test@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertNotNull($response->json('expires_at'));

        $token = $this->admin->tokens()->where('name', 'backoffice')->latest('id')->first();
        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_expired_token_cannot_access_admin_routes(): void
    {
        $plain = $this->admin->createToken('backoffice', ['*'], now()->subMinute())->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_login_revokes_previous_backoffice_tokens(): void
    {
        $old = $this->admin->createToken('backoffice', ['*'], now()->addDay())->plainTextToken;

        $this->postJson('/api/login', [
            'email' => 'session_ttl_test@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$old)
            ->getJson('/api/admin/me')
            ->assertUnauthorized();

        $this->assertSame(1, $this->admin->tokens()->where('name', 'backoffice')->count());
    }

    public function test_logout_revokes_current_token(): void
    {
        $plain = $this->admin->createToken('backoffice', ['*'], now()->addDay())->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/admin/logout')
            ->assertOk();

        // Asserted on storage: the guard caches the resolved user for the rest of the test process.
        $this->assertSame(0, $this->admin->tokens()->where('name', 'backoffice')->count());
    }
}
