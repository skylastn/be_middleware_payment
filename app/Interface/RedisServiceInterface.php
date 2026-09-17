<?php

namespace App\Interface;

use Closure;

interface RedisServiceInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function setex(string $key, int $ttl, mixed $value): bool;

    public function has(string $key): bool;

    public function del(string ...$keys): int;

    public function delete(string ...$keys): int;

    public function remember(string $key, int $ttl, Closure $callback): mixed;

    public function increment(string $key, int $amount = 1): int;

    public function decrement(string $key, int $amount = 1): int;

    public function lock(string $key, int $ttl = 5): bool;

    public function unlock(string $key): bool;

    public function generatePaymentToken(int $projectId, string $projectValue, string $reference, int $ttl = 7200): string;

    public function getPaymentToken(string $token): ?array;

    public function deletePaymentToken(string $token): void;

    public function storeSnapAccessToken(string $token, string $clientKey, int $ttl = 900): void;

    public function getSnapAccessToken(string $token): ?array;
}
