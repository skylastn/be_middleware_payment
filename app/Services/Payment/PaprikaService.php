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
use InvalidArgumentException;
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

    public static function getPublicKey(string $privateKey): string
    {
        return openssl_pkey_get_details(
            openssl_get_privatekey($privateKey)
        )['key'];
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

    public function orderPaprika(Request $request, Project $project)
    {
        Log::debug('Paprika QR MPM: Start', [
            'project_id' => $project->id,
            'request_ip' => $request->ip(),
        ]);

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


        $token = $this->getB2BToken($paymentRepo);

        if(!isset($token['accessToken'])) {
            throw new Error('access token is fail to genrate');
        }


        // Timestamp
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = $this->generateSignature($paymentRepo);

        // Headers
        $headers = [
            'Authorization' => 'Bearer ' . $token['accessToken'],
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature['X-SIGNATURE'],
            'X-PARTNER-ID' => $this->dailyUnique('X-PARTNER-ID', 28),
            'X-EXTERNAL-ID' => $this->dailyUnique('X-EXTERNAL-ID',28),
            'CHANNEL-ID' => 95221,
        ];


        // Request
        Log::debug('Paprika QR MPM: Sending request', [
            'method' => 'POST',
            'url' => $url,
            'headers' => $headers,
            'body' => $body
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->post($url, $body);

            Log::debug('Paprika QR MPM: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Paprika QR MPM: HTTP request exception', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        if ($response->successful()) {

            return $response->json();
        }

        Log::error('Paprika QR MPM: Failed to generate QR', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \Exception(
            'Failed to generate QR MPM: ' . $response->body()
        );
    }

    public function callback(Request $request) {}

    function dailyUnique(string $param, int $length = 36): string
    {
        if ($length < 1 || $length > 36) {
            throw new InvalidArgumentException('Length must be between 1 and 36.');
        }

        $date = now()->format('Y-m-d');

        $hash = hash_hmac(
            'sha256',
            "{$param}|{$date}",
            config('app.key')
        );

        $numeric = '';

        foreach (str_split($hash, 2) as $chunk) {
            $numeric .= str_pad((string) hexdec($chunk), 3, '0', STR_PAD_LEFT);
        }

        return substr($numeric, 0, $length);
    }

}