<?php

namespace App\Services\Socket;

use App\Http\Helper\FormatHelper;
use App\Models\Response\Socket\SocketMiddlewareResponse;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocketNetworkService
{
    private string $socketUrl;
    private ?string $path;
    private ?array $body;
    public function __construct(string $socketUrl, ?string $path = null, ?array $body = null)
    {
        $this->socketUrl = $socketUrl;
        $this->path = $path;
        $this->body = $body;
    }

    public function sendNotif(): ?SocketMiddlewareResponse
    {
        $response = Http::asJson()->post($this->socketUrl . ($this->path ?? ''), $this->body ?? []);
        if (!FormatHelper::contains($response->status(), [200, 201])) {
            Log::info("SocketNetworkService: Error sendNotif", [
                'url' => $this->socketUrl,
                'path' => $this->path,
                'body' => $this->body,
                'response' => $response->json(),
            ]);
            throw new Exception("error sendNotif to socket service", $response->json());
        }
        return SocketMiddlewareResponse::from((object)$response->json());
    }
}
