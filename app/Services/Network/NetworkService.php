<?php

namespace App\Services\Network;

use App\Enums\NetworkType;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class NetworkService
{
    private string $url = '';
    private Request $request;
    public function __construct(
        string $url = '',
        NetworkType $method = NetworkType::GET,
        array $header = [],
        ?array $body = null
    ) {

        $this->url = $url;
        $this->request = new Request(
            $method->value,
            $this->url,
            $header,
            json_encode($body)
        );
    }

    function sendAsync(): ?string
    {
        $client = new Client();
        // Send an asynchronous request.
        $promise = $client->sendAsync($this->request);

        $response = $promise->wait();
        return $response->getBody()->getContents();
    }
}
