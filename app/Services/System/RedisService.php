<?php

namespace App\Services\System;

use App\Interface\RedisServiceInterface;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisService implements RedisServiceInterface
{
    private const DEFAULT_TTL = 7200; // 2 jam

    public function __construct(private mixed $client = null) {}

    protected function getClient(): mixed
    {
        return $this->client ?? Redis::getFacadeRoot();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->getClient()->get($key);

        if ($data === null || $data === false) {
            return $default;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                return $decoded;
            }
        }

        return $data;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $payload = is_array($value) || is_object($value) ? json_encode($value) : $value;

        if ($ttl !== null && $ttl > 0) {
            return (bool) $this->getClient()->setex($key, $ttl, $payload);
        }

        return (bool) $this->getClient()->set($key, $payload);
    }

    public function setex(string $key, int $ttl, mixed $value): bool
    {
        return $this->set($key, $value, $ttl);
    }

    public function has(string $key): bool
    {
        return (bool) $this->getClient()->exists($key);
    }

    public function del(string ...$keys): int
    {
        if (empty($keys)) {
            return 0;
        }

        return (int) $this->getClient()->del(...$keys);
    }

    public function delete(string ...$keys): int
    {
        return $this->del(...$keys);
    }

    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $fresh = $callback();
        $this->set($key, $fresh, $ttl);

        return $fresh;
    }

    public function increment(string $key, int $amount = 1): int
    {
        return (int) ($amount === 1
            ? $this->getClient()->incr($key)
            : $this->getClient()->incrby($key, $amount));
    }

    public function decrement(string $key, int $amount = 1): int
    {
        return (int) ($amount === 1
            ? $this->getClient()->decr($key)
            : $this->getClient()->decrby($key, $amount));
    }

    public function lock(string $key, int $ttl = 5): bool
    {
        try {
            $result = $this->getClient()->set($key, 1, 'EX', $ttl, 'NX');

            return (bool) $result;
        } catch (\Throwable) {
            return true; // fail-open so Redis error does not block transactions
        }
    }

    public function unlock(string $key): bool
    {
        try {
            return (bool) $this->del($key);
        } catch (\Throwable) {
            return false;
        }
    }

    public function generatePaymentToken(int $projectId, string $projectValue, string $reference, int $ttl = self::DEFAULT_TTL): string
    {
        $token = Str::random(40);

        $this->set("payment_token:{$token}", [
            'project_id' => $projectId,
            'project_value' => $projectValue,
            'reference' => $reference,
        ], $ttl);

        return $token;
    }

    public function getPaymentToken(string $token): ?array
    {
        $data = $this->get("payment_token:{$token}");

        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    public function deletePaymentToken(string $token): void
    {
        $this->del("payment_token:{$token}");
    }

    public function storeSnapAccessToken(string $token, string $clientKey, int $ttl = 900): void
    {
        $this->set("snap_token:{$token}", [
            'client_key' => $clientKey,
            'created_at' => time(),
        ], $ttl);
    }

    public function getSnapAccessToken(string $token): ?array
    {
        $data = $this->get("snap_token:{$token}");

        if (! is_array($data)) {
            return null;
        }

        return $data;
    }
}
