<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisService
{
    private const DEFAULT_TTL = 7200; // 2 jam

    public function generatePaymentToken(int $projectId, string $projectValue, string $reference, int $ttl = self::DEFAULT_TTL): string
    {
        $token = Str::random(40);

        Redis::setex("payment_token:{$token}", $ttl, json_encode([
            'project_id'    => $projectId,
            'project_value' => $projectValue,
            'reference'     => $reference,
        ]));

        return $token;
    }

    public function getPaymentToken(string $token): ?array
    {
        $data = Redis::get("payment_token:{$token}");

        if (! $data) {
            return null;
        }

        return json_decode($data, true);
    }

    public function deletePaymentToken(string $token): void
    {
        Redis::del("payment_token:{$token}");
    }
}
