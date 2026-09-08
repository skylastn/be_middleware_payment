<?php

namespace App\Repository\Payment;

use App\Enums\NetworkType;
use App\Services\Network\NetworkService;
use Duitku\Config;
use Exception;
use GuzzleHttp\Exception\RequestException;

class DuitkuRepository
{
    public function createInvoice(array $params, Config $config): ?string
    {
        $apiUrl = (string) $config->getApiUrl();
        $url = rtrim($apiUrl, '/') . '/webapi/api/merchant/v2/inquiry';

        try {
            $result = (new NetworkService(
                $url,
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
