<?php

namespace App\Http\Helper;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

class RequestHelper
{
    public static function sendCallback(string $token, array $params, string $urlCallback): stdClass
    {
        $response = Http::connectTimeout(5)
            ->timeout(15)
            ->withHeaders(['Token' => $token, 'Content-Type' => 'application/json'])
            ->post($urlCallback, $params)
            ->throw();

        if (! $response->successful()) {
            throw new \RuntimeException('Merchant callback did not acknowledge delivery');
        }

        Log::info('Merchant callback delivered', [
            'merchantOrderId' => $params['merchantOrderId'] ?? null,
            'status' => $response->status(),
        ]);

        return (object) ($response->json() ?? []);
    }
}
