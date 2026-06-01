<?php

namespace App\Http\Helper;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

class RequestHelper
{
    public static function sendCallback(string $token, array $params, string $urlCallback): stdClass
    {
        Log::info('Sending Request', [$urlCallback, $params, $token]);

        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'Token' => $token,
                    'Content-Type' => 'application/json',
                ])
                ->post($urlCallback, $params);

            $decoded = $response->json();

            Log::info('Result Callback', [$response->body()]);

            return $decoded instanceof stdClass ? $decoded : (object) $decoded;
        } catch (\Exception $e) {
            Log::error('Callback failed', ['url' => $urlCallback, 'error' => $e->getMessage()]);

            return new stdClass;
        }
    }
}
