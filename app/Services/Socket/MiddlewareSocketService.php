<?php

namespace App\Services\Socket;

use App\Model\Response\Socket\SocketMiddlewareResponse;
use Illuminate\Support\Facades\Log;

class MiddlewareSocketService
{
    private ?string $project;
    private ?string $path;
    private ?array $body;
    public function __construct(?string $project = null, ?string $path = null, ?array $body = null)
    {
        $this->project = $project;
        $this->path = $path;
        $this->body = $body;
    }

    public function sendNotif(): ?SocketMiddlewareResponse
    {
        $url = env("SOCKET_API_URL") . '/api/';
        $pathSocket = ($this->project ?? '')  . '/' . ($this->path ?? '');
        Log::info("MiddlewareSocketService: sendNotif", [
            'url' => $url,
            'path' => $pathSocket,
            'body' => $this->body
        ]);
        $result = (new SocketNetworkService($url, $pathSocket, $this->body))->sendNotif();
        Log::info("MiddlewareSocketService: sendNotif result", [
            $result->to(),
        ]);
        return $result;
    }
}
