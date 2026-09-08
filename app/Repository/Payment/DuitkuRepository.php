<?php

namespace App\Repository\Payment;

use App\Services\Network\NetworkService;
use Duitku\Config;
use Exception;

class DuitkuRepository
{
    public function __construct(
        private ?NetworkService $networkService = null
    ) {
        $this->networkService ??= new NetworkService();
    }

    public function createInvoice(array $params, Config $config): ?string
    {
        $apiUrl = (string) $config->getApiUrl();
        $url = rtrim($apiUrl, '/') . '/webapi/api/merchant/v2/inquiry';

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen(json_encode($params)),
            ];

            $result = $this->networkService->post($url, $headers, $params);

            if ($result) {
                $decode = json_decode($result);
                if (isset($decode->Message)) {
                    throw new Exception($decode->Message);
                }
            }

            return $result;
        } catch (Exception $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw new Exception($ex->getMessage());
        }
    }
}
