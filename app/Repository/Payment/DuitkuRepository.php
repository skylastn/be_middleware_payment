<?php

namespace App\Repository\Payment;

use App\Services\Network\NetworkService;
use Duitku\Config;
use Exception;
use Illuminate\Support\Facades\Http;

class DuitkuRepository
{
    public function __construct(
        private ?NetworkService $networkService = null
    ) {
        $this->networkService ??= new NetworkService();
    }

    public function checkStatus(string $merchantOrderId, Config $config): string
    {
        return Http::connectTimeout(5)->timeout(20)->post(rtrim($config->getApiUrl(), '/') . '/webapi/api/merchant/transactionStatus', [
            'merchantCode' => $config->getMerchantCode(), 'merchantOrderId' => $merchantOrderId,
            'signature' => hash_hmac('sha256', $config->getMerchantCode().$merchantOrderId, $config->getApiKey()),
        ])->throw()->body();
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

            $result = Http::connectTimeout(5)->timeout(45)->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $params)->throw()->body();

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
