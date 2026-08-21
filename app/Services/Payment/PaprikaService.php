<?php

namespace App\Services\Payment;

use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaprikaService {
    private PaymentRepositoryService $paymentRepositoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('paprika', $modeValue);
    }

    public function generateSignature(PaymentRepository $paymentRepo): array
    {
        $paymentConfig = $paymentRepo->getValue();

        $clientKey = $paymentConfig['SNAP_CLIENT_KEY'];
        $privateKey = $paymentConfig['PRIVATE_KEY'];

        $privateKey = str_replace('\n', "\n", $privateKey);

        $timestamp = date('c');

        $stringToSign = "{$clientKey}|{$timestamp}";

        $signature = $this->signByAsymmetricSignature(
            $stringToSign,
            $privateKey
        );

        return [
            'X-CLIENT-KEY' => $clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }

    public function signByAsymmetricSignature(string $stringToSign,string $privateKey): string {
        
        $privateKey = str_replace('\n', "\n", $privateKey);
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new \RuntimeException(
                'Invalid private key: ' .
                (openssl_error_string() ?: 'Unknown OpenSSL error')
            );
        }

        $result = openssl_sign(
            $stringToSign,
            $signature,
            $key,
            OPENSSL_ALGO_SHA256
        );

        if ($result === false) {
            throw new \RuntimeException(
                'Failed to generate signature: ' .
                (openssl_error_string() ?: 'Unknown OpenSSL error')
            );
        }

        return base64_encode($signature);
    }

    function getB2BToken(PaymentRepository $paymentRepo)
    {
       $clientKey = $paymentRepo->getValue()['SNAP_CLIENT_KEY'];
       $clientSecret = $paymentRepo->getValue()['SNAP_CLIENT_SECRET'];
   
       $url = 'http://staging-gateway.paprika.co.id/api/snap/v1.0/access-token/b2b';
   
       $headerGeneration = $this->generateSignature($paymentRepo);
   
       $headers = [
           'Content-Type' => 'application/json',
           'X-CLIENT-KEY' => $headerGeneration['X-CLIENT-KEY'],
           'X-TIMESTAMP' => $headerGeneration['X-TIMESTAMP'],
           'X-SIGNATURE' => $headerGeneration['X-SIGNATURE'],
       ];
   
       $body = [
           'grantType' => 'client_credentials',
       ];
   
       Log::info('SNAP B2B Token Request', [
           'url' => $url,
           'method' => 'POST',
           'headers' => $headers,
           'body' => $body,
           'SNAP_CLIENT_KEY' => $clientKey,
           'SNAP_CLIENT_SECRET' => $clientSecret,
       ]);
   
       $response = Http::withHeaders($headers)
           ->post($url, $body);
   
       Log::info('SNAP B2B Token Response', [
           'http_code' => $response->status(),
           'response' => $response->body(),
       ]);
   
       if ($response->successful()) {
           return $response->json();
       }
   
       throw new \Exception(
           'Failed to get B2B token: ' . $response->body()
       );
    }

    public function orderPaprika(Request $request, Project $project) {

        $paymentRepo = $this->getPaymentRepo(
            PaymentModeType::sandbox->value,
            null
        );

        // Endpoint URL API Generate QR MPM
        $url = 'http://staging-gateway.paprika.co.id/api/snap/v1.0/qr/qr-mpm-generate';

        // Data body request
        $body = [
            'partnerReferenceNo' => 'GTEST' . now()->format('YmdHis'),
            'amount' => [
                'value' => '100.00',
                'currency' => 'IDR',
            ],
        ];

        // Generate B2B token
        $token = $this->getB2BToken($paymentRepo);

        Log::debug('SNAP B2B Token', [
            'token' => $token,
        ]);

        // Timestamp
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        // Generate signature
        $signature = $this->generateSignature($paymentRepo);

        // Headers
        $headers = [
            'Authorization' => 'Bearer ' . $token['accessToken'],
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature['X-SIGNATURE'],
            'X-PARTNER-ID' => 'PARTNER_ID_ANDA',
            'X-EXTERNAL-ID' => 'EXTERNAL_ID_UNIK_ANDA',
            'CHANNEL-ID' => 'CHANNEL_ID_ANDA',
        ];

        // Request
        $response = Http::withHeaders($headers)
            ->post($url, $body);

        // Log response
        Log::debug('SNAP QR MPM Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception(
            'Failed to generate QR MPM: ' . $response->body()
        );
    }

    public function callback(Request $request) {}

}