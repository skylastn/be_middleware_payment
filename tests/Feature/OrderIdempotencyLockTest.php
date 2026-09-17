<?php

namespace Tests\Feature;

use App\Interface\RedisServiceInterface;
use App\Model\Entity\Project;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderIdempotencyLockTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Lock Test Project',
            'type' => 'LCK',
            'slug' => 'duitku',
            'key' => 'lockkey123',
            'secure' => 'locksecure123',
            'value' => Str::random(60),
            'callback' => 'https://example.com/callback',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->project && $this->project->exists) {
            $this->project->forceDelete();
        }

        parent::tearDown();
    }

    public function test_concurrent_order_create_triggers_idempotency_lock(): void
    {
        $redis = app(RedisServiceInterface::class);
        $reference = 'LCK-INV-'.uniqid();
        $lockKey = "lock:order:create:{$this->project->type}:{$reference}";

        // Simulate an ongoing concurrent request holding the lock
        $redis->lock($lockKey, 5);

        $response = $this->withHeaders([
            'Token' => $this->project->value,
        ])->postJson('/api/order/create', [
            'merchantOrderId' => $reference,
            'paymentAmount' => 100000,
            'paymentMethod' => 'VC',
            'productDetails' => 'Test Product',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('code', 409);
        $response->assertJsonPath('message', 'Duplicate Order Request');
        $response->assertJsonPath('data', 'Duplicate order request detected. Please wait a few seconds before retrying.');

        // Cleanup lock
        $redis->unlock($lockKey);
    }

    public function test_concurrent_client_payment_triggers_idempotency_lock(): void
    {
        $redis = app(RedisServiceInterface::class);
        $reference = 'LCK-CLI-'.uniqid();
        $token = $redis->generatePaymentToken($this->project->id, $this->project->value, $reference, 60);

        $lockKey = "lock:client:payment:{$reference}";
        $redis->lock($lockKey, 5);

        $response = $this->withHeaders([
            'Token' => $token,
        ])->postJson('/api/client/order/createPayment', [
            'reference' => $reference,
            'paymentMethod' => 'VC',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('status', false);
        $response->assertJsonPath('code', 409);
        $response->assertJsonPath('message', 'Duplicate Payment Request');
        $response->assertJsonPath('data', 'Payment request is already being processed. Please wait.');

        $redis->unlock($lockKey);
        $redis->deletePaymentToken($token);
    }
}
