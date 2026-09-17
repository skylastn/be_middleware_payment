<?php

namespace App\Http\Helper;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

class RequestHelper
{
    public static function sendCallback(string $token, array $params, string $urlCallback, bool $throwOnError = false): stdClass
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

            $body = $response->body();

            if (! $response->successful()) {
                Log::error('Callback failed', [
                    'url' => $urlCallback,
                    'status' => $response->status(),
                    'response' => $body,
                ]);

                if ($throwOnError) {
                    throw new \RuntimeException("Callback to {$urlCallback} failed with status {$response->status()}: {$body}");
                }

                return new stdClass;
            }

            Log::info('Result Callback', [$body]);

            $decoded = $response->json();

            return $decoded instanceof stdClass ? $decoded : (object) $decoded;
        } catch (\Throwable $e) {
            $fullMessage = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $stream = $e->getResponse()->getBody();
                    if ($stream->isReadable()) {
                        $stream->rewind();
                        $fullBody = (string) $stream;
                        if (! empty($fullBody)) {
                            $fullMessage .= "\nFull Response: ".$fullBody;
                        }
                    }
                } catch (\Throwable) {
                }
            }

            Log::error('Callback failed', [
                'url' => $urlCallback,
                'error' => $fullMessage,
            ]);

            if ($throwOnError) {
                throw $e;
            }

            return new stdClass;
        }
    }
}
