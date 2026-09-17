<?php

namespace Tests\Feature;

use App\Interface\RedisServiceInterface;
use App\Services\System\RedisService;
use Tests\TestCase;

class RedisServiceTest extends TestCase
{
    public function test_redis_service_is_bound_in_container(): void
    {
        $resolved = app(RedisServiceInterface::class);
        $this->assertInstanceOf(RedisService::class, $resolved);

        $concrete = app(RedisService::class);
        $this->assertInstanceOf(RedisService::class, $concrete);
        $this->assertSame($resolved, $concrete);
    }

    public function test_redis_service_set_and_get(): void
    {
        $redis = app(RedisServiceInterface::class);
        $key = 'test:key:'.uniqid();

        $redis->set($key, ['hello' => 'world', 'count' => 42], 60);

        $this->assertTrue($redis->has($key));
        $this->assertEquals(['hello' => 'world', 'count' => 42], $redis->get($key));

        $redis->del($key);
        $this->assertFalse($redis->has($key));
        $this->assertNull($redis->get($key));
    }

    public function test_redis_service_remember(): void
    {
        $redis = app(RedisServiceInterface::class);
        $key = 'test:remember:'.uniqid();

        $called = 0;
        $val1 = $redis->remember($key, 60, function () use (&$called) {
            $called++;

            return 'computed_val';
        });

        $val2 = $redis->remember($key, 60, function () use (&$called) {
            $called++;

            return 'new_val';
        });

        $this->assertEquals('computed_val', $val1);
        $this->assertEquals('computed_val', $val2);
        $this->assertEquals(1, $called);

        $redis->del($key);
    }

    public function test_redis_service_tokens(): void
    {
        $redis = app(RedisServiceInterface::class);

        $token = $redis->generatePaymentToken(123, 'proj-val', 'REF-999', 60);
        $this->assertNotEmpty($token);

        $data = $redis->getPaymentToken($token);
        $this->assertNotNull($data);
        $this->assertEquals(123, $data['project_id']);
        $this->assertEquals('proj-val', $data['project_value']);
        $this->assertEquals('REF-999', $data['reference']);

        $redis->deletePaymentToken($token);
        $this->assertNull($redis->getPaymentToken($token));
    }

    public function test_redis_service_lock_and_unlock(): void
    {
        $redis = app(RedisServiceInterface::class);
        $lockKey = 'test:lock:'.uniqid();

        $this->assertTrue($redis->lock($lockKey, 5));
        $this->assertFalse($redis->lock($lockKey, 5)); // Second attempt within TTL fails

        $this->assertTrue($redis->unlock($lockKey));
        $this->assertTrue($redis->lock($lockKey, 5)); // After unlock, acquire succeeds

        $redis->unlock($lockKey);
    }

    public function test_redis_service_queue_data_structures_and_info(): void
    {
        $redis = app(RedisServiceInterface::class);
        $listKey = 'test:queue:list:'.uniqid();
        $zsetKey = 'test:queue:zset:'.uniqid();

        // Test List methods (used for pending jobs)
        $this->assertEquals(0, $redis->llen($listKey));
        $this->assertEquals([], $redis->lrange($listKey, 0, -1));

        // Test ZSet methods (used for delayed/reserved jobs)
        $this->assertEquals(0, $redis->zcard($zsetKey));
        $this->assertEquals([], $redis->zrange($zsetKey, 0, -1));

        // Test info method
        $info = $redis->info();
        $this->assertIsArray($info);
    }
}
