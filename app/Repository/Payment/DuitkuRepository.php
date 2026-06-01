<?php

namespace App\Repository\Payment;

use App\Enums\NetworkType;
use App\Services\Network\NetworkService;
use Duitku\Config;
use Exception;
use GuzzleHttp\Exception\RequestException;

class DuitkuRepository
{
    private Config $config;
    private string $url;
    public function __construct(Config $config)
    {
        $this->config = $config;
        $apiUrl = (string) $this->config->getApiUrl();
        $this->url = rtrim($apiUrl, '/') . '/webapi/api/merchant/v2/';
    }
    public function createInvoice(array $params): ?string
    {
        try {
            $result = (new NetworkService(
                $this->url . 'inquiry',
                NetworkType::POST,
                array(
                    'Content-Type' => 'application/json',
                    'Content-Length' => strlen(json_encode($params))
                ),
                $params,
            ))->sendAsync();
            return $result;
        } catch (RequestException $ex) {
            if ($ex->hasResponse()) {
                $decode = json_decode($ex->getResponse()->getBody()->getContents());
                throw new Exception($decode->Message);
            }
            throw new Exception($ex->getMessage());
        }
    }
}
