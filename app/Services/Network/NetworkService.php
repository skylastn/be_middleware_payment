<?php

namespace App\Services\Network;

use App\Enums\NetworkType;
use Illuminate\Support\Facades\Http;

class NetworkService
{
    private string $url;
    private NetworkType $method;
    private array $header;
    private ?array $body;

    public function __construct(
        string $url = '',
        NetworkType $method = NetworkType::GET,
        array $header = [],
        ?array $body = null
    ) {
        $this->url = $url;
        $this->method = $method;
        $this->header = $header;
        $this->body = $body;
    }

    public function send(
        string $url,
        NetworkType $method = NetworkType::GET,
        array $header = [],
        ?array $body = null
    ): ?string {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }

        $response = Http::withHeaders($header)
            ->timeout(60)
            ->connectTimeout(10)
            ->send($method->value, $url, $options);

        return $response->body();
    }

    public function post(string $url, array $header = [], ?array $body = null): ?string
    {
        return $this->send($url, NetworkType::POST, $header, $body);
    }

    public function get(string $url, array $header = []): ?string
    {
        return $this->send($url, NetworkType::GET, $header);
    }

    public function sendAsync(): ?string
    {
        return $this->send($this->url, $this->method, $this->header, $this->body);
    }
}
