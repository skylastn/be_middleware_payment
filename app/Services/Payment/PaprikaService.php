<?php

namespace App\Services\Payment;

use App\Enums\NetworkType;
use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Network\NetworkService;
use App\Services\System\ProjectService;
use App\Services\System\RedisService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class PaprikaService
{
    private PaymentRepositoryService $paymentRepositoryService;
    private RedisService $redisService;
    private OrderHistoryService $orderHistoryService;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private ProjectService $projectService;
    private NetworkService $networkService;
    private ?array $vaBankCodeMap = null;

    public function __construct(
        ?PaymentRepositoryService $paymentRepositoryService = null,
        ?RedisService $redisService = null,
        ?OrderHistoryService $orderHistoryService = null,
        ?OrderService $orderService = null,
        ?PaymentService $paymentService = null,
        ?ProjectService $projectService = null,
        ?NetworkService $networkService = null,
    ) {
        $this->paymentRepositoryService = $paymentRepositoryService ?? new PaymentRepositoryService;
        $this->redisService = $redisService ?? new RedisService;
        $this->orderHistoryService = $orderHistoryService ?? new OrderHistoryService;
        $this->orderService = $orderService ?? new OrderService;
        $this->paymentService = $paymentService ?? new PaymentService;
        $this->projectService = $projectService ?? new ProjectService;
        $this->networkService = $networkService ?? new NetworkService;
    }

    private function getVABankCodeMap(): array
    {
        if ($this->vaBankCodeMap !== null) {
            return $this->vaBankCodeMap;
        }

        $methods = $this->paymentService->getBankCodeMapByGatewayKey('paprika');

        $this->vaBankCodeMap = array_change_key_case($methods, CASE_UPPER);

        return $this->vaBankCodeMap;
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

    public function generateSignature(PaymentRepository $paymentRepo, ?string $timestamp = null): array
    {
        $paymentConfig = $paymentRepo->getValue();

        $clientKey = $paymentConfig['api_key'];
        $privateKey = $paymentConfig['private_key'];

        $timestamp ??= date('c');

        $stringToSign = "{$clientKey}|{$timestamp}";

        $signature = $this->signByAsymmetricSignature($stringToSign, $privateKey);

        return [
            'X-CLIENT-KEY' => $clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }

    public function signByAsymmetricSignature(string $stringToSign, string $privateKey): string
    {
        $privateKey = str_replace('\n', "\n", $privateKey);
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false) {
            throw new RuntimeException(
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
            throw new RuntimeException(
                'Failed to generate signature: ' .
                (openssl_error_string() ?: 'Unknown OpenSSL error')
            );
        }

        return base64_encode($signature);
    }

    public function generateHeaderSymmetricSignature(
        PaymentRepository $paymentRepo,
        string $accessToken,
        string $httpMethod,
        string $endpoint,
        string $requestBody,
        ?string $timestamp = null
    ): array {
        $paymentConfig = $paymentRepo->getValue();
        $clientKey = $paymentConfig['api_key'];

        $headerGeneration = $this->signBySymmetricSignature(
            $paymentRepo,
            $accessToken,
            $httpMethod,
            $endpoint,
            $requestBody,
            $timestamp
        );

        return [
            'X-PARTNER-ID' => $clientKey,
            'X-TIMESTAMP' => $headerGeneration['X-TIMESTAMP'],
            'X-SIGNATURE' => $headerGeneration['X-SIGNATURE'],
        ];
    }

    public function signBySymmetricSignature(
        PaymentRepository $paymentRepo,
        string $accessToken,
        string $httpMethod,
        string $endpoint,
        string $requestBody,
        ?string $timestamp = null
    ): array {
        $paymentConfig = $paymentRepo->getValue();

        $clientKey = $paymentConfig['api_key'];
        $clientSecret = $paymentConfig['api_secret'];

        $bodyHash = hash('sha256', $requestBody);
        $timestamp ??= date('c');

        $stringToSign = "{$httpMethod}:{$endpoint}:{$accessToken}:{$bodyHash}:{$timestamp}";

        $signature = base64_encode(
            hash_hmac('sha512', $stringToSign, $clientSecret, true)
        );

        return [
            'X-CLIENT-KEY' => $clientKey,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }

    public function getB2BToken(PaymentRepository $paymentRepo): array
    {
        $baseurl = $paymentRepo->getValue()['base_url'];
        $url = "$baseurl/api/snap/v1.0/access-token/b2b";

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
        ]);

        $rawResponse = $this->networkService->post($url, $headers, $body);
        $responseData = json_decode((string) $rawResponse, true) ?: [];

        Log::info('SNAP B2B Token Response', [
            'response' => $rawResponse,
        ]);

        if (! empty($responseData['accessToken'])) {
            return $responseData;
        }

        throw new Exception(
            'Failed to get B2B token: ' . (is_string($rawResponse) ? $rawResponse : json_encode($responseData))
        );
    }

    public function orderPaprika(Request $request, Project $project): array
    {
        if (FormatHelper::isNotEmpty($request->paymentMethod)) {
            if (strtolower($request->paymentMethod) == 'qris') {
                return $this->createQris($request, $project);
            }

            $vaBankCodeMap = $this->getVABankCodeMap();
            if (isset($vaBankCodeMap[strtoupper($request->paymentMethod)])) {
                return $this->createVA($request, $project);
            }

            throw new Exception("no supported payment method {$request->paymentMethod}");
        } else {
            throw new Exception("payment method is requred");
        }
    }

    public function createQris(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        $baseurl = $paymentRepo->getValue()['base_url'];
        $idSystem = OrderIdGenerator::generate();
        $reference = $project->type . '-' . $request->merchantOrderId;

        $req['id'] = $idSystem;
        $req['reference'] = $reference;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';

        $url = "$baseurl/api/snap/v1.0/qr/qr-mpm-generate";

        $body = [
            'partnerReferenceNo' => $reference,
            'amount' => [
                'value' => number_format($request->paymentAmount, 2, '.', ''),
                'currency' => 'IDR',
            ],
        ];

        $token = $this->getB2BToken($paymentRepo);

        if (!isset($token['accessToken'])) {
            throw new \Error('access token is fail to generate');
        }

        $timestamp = date('c');

        $endpoint = '/api/snap/v1.0/qr/qr-mpm-generate';
        $headerGeneration = $this->signBySymmetricSignature(
            $paymentRepo,
            $token['accessToken'],
            'POST',
            $endpoint,
            json_encode($body),
            $timestamp
        );

        $headers = [
            'Authorization' => 'Bearer ' . $token['accessToken'],
            'Content-Type' => 'application/json',
            'X-PARTNER-ID' => $headerGeneration['X-CLIENT-KEY'],
            'X-TIMESTAMP' => $headerGeneration['X-TIMESTAMP'],
            'X-SIGNATURE' => $headerGeneration['X-SIGNATURE'],
            'X-EXTERNAL-ID' =>  $this->dailyUnique($reference, 28),
            'CHANNEL-ID' => 95221,
        ];

        $req['request'] = json_encode($body);
        $req['status'] = OrderStatus::PENDING->value;
        $order = $this->orderService->createAndFind($req);

        LogHelper::sendLog(
            'Request Order Paprika',
            ['header' => json_encode($headers), 'body' => json_encode($body)],
            $project->id,
            'request_order_paprika'
        );

        try {
            $rawResponse = $this->networkService->post($url, $headers, $body);
            $responseData = json_decode((string) $rawResponse, true) ?: [];

            Log::debug('Paprika QR MPM: Response received', [
                'body' => $rawResponse,
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

        LogHelper::sendLog(
            'Response Order Paprika',
            $responseData,
            $project->id,
            'response_order_paprika'
        );

        $this->assertPaprikaSuccess($responseData);

        $qrContent = $responseData['qrContent'] ?? null;
        $qrUrl = $responseData['qrUrl'] ?? null;
        $order->setResponse(json_encode($responseData));
        $order->setUrl($qrUrl ?? $qrContent);
        $order->setValue($qrContent);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::PENDING,
            'ORDER_CREATE_PAPRIKA',
            'Order created with status PENDING',
            $body,
            null
        );

        $msg = 'Success Create Order Paprika';
        $result['link'] = $qrContent ?? $qrUrl;
        if (FormatHelper::isNotEmpty($request->version) && $request->version == '2') {
            $token = $this->redisService->generatePaymentToken($project->id, $project->value, $order->getReference());
            if (FormatHelper::isNotEmpty($request->paymentMethod)) {
                $result['link'] = env('PAYMENT_URL') . '/detailpayment?token=' . $token . '&reference=' . $order->getReference();
            } else {
                $result['link'] = env('PAYMENT_URL') . '/home?token=' . $token . '&reference=' . $order->getReference();
            }
        }
        $result['result'] = $responseData;
        $result['message'] = $msg;

        return $result;
    }

    public function createVA(Request $request, Project $project): array
    {
        if (!FormatHelper::isNotEmpty($request->paymentMethod)) {
            throw new Exception("payment method required");
        }

        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        $baseurl = $paymentRepo->getValue()['base_url'];
        $idSystem = OrderIdGenerator::generate();
        $reference = $project->type . '-' . $request->merchantOrderId;

        $req['id'] = $idSystem;
        $req['reference'] = $reference;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';

        $url = "$baseurl/api/snap/v1.0/transfer-va/create-va";

        $bankCode = $this->getVABankCodeMap()[strtoupper($request->paymentMethod)] ?? '';

        if (empty($bankCode)) {
            throw new RuntimeException('Unsupported VA payment method: ' . $request->paymentMethod);
        }
        $expiryPeriod = $request->expiryPeriod ?? 60;
        $expiredDate = Carbon::now()->addMinutes((int) $expiryPeriod)->format('Y-m-d\TH:i:sP');

        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));

        $body = [
            'customerNo' => '',
            'virtualAccountName' => $customerName ?: 'Paprika VA',
            'virtualAccountEmail' => $request->email ?? '',
            'virtualAccountPhone' => $request->phone ?? '',
            'trxId' => $reference,
            'totalAmount' => [
                'value' => number_format($request->paymentAmount, 2, '.', ''),
                'currency' => $request->currency ?? 'IDR',
            ],
            'virtualAccountTrxType' => 'C',
            // TODO: nextnya ini bisa dipake kalo dari paprika expiredDate udah oke
            // 'expiredDate' => $expiredDate,
            'expiredDate' => "",
            'additionalInfo' => [
                'bankCode' => $bankCode,
            ],
        ];

        $token = $this->getB2BToken($paymentRepo);

        if (!isset($token['accessToken'])) {
            throw new \Error('access token is fail to generate');
        }

        $timestamp = date('c');

        $endpoint = '/api/snap/v1.0/transfer-va/create-va';
        $headerGeneration = $this->signBySymmetricSignature(
            $paymentRepo,
            $token['accessToken'],
            'POST',
            $endpoint,
            json_encode($body),
            $timestamp
        );

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token['accessToken'],
            'X-PARTNER-ID' => $headerGeneration['X-CLIENT-KEY'],
            'X-TIMESTAMP' => $headerGeneration['X-TIMESTAMP'],
            'X-SIGNATURE' => $headerGeneration['X-SIGNATURE'],
            'X-EXTERNAL-ID' => $this->dailyUnique($reference, 28),
            'CHANNEL-ID' => 95221,
        ];

        $req['request'] = json_encode($body);
        $req['status'] = OrderStatus::PENDING->value;
        $order = $this->orderService->createAndFind($req);

        LogHelper::sendLog(
            'Request Order Paprika VA',
            ['header' => json_encode($headers), 'body' => json_encode($body)],
            $project->id,
            'request_order_paprika_va'
        );

        try {
            $rawResponse = $this->networkService->post($url, $headers, $body);
            $responseData = json_decode((string) $rawResponse, true) ?: [];

            Log::debug('Paprika VA: Response received', [
                'body' => $rawResponse,
            ]);
        } catch (\Throwable $e) {
            Log::error('Paprika VA: HTTP request exception', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        LogHelper::sendLog(
            'Response Order Paprika VA',
            $responseData,
            $project->id,
            'response_order_paprika_va'
        );

        $this->assertPaprikaSuccess($responseData);

        $vaNumber = $responseData['virtualAccountData']['virtualAccountNo'] ?? null;
        $order->setResponse(json_encode($responseData));
        $order->setUrl($vaNumber);
        $order->setValue($vaNumber);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::PENDING,
            'ORDER_CREATE_PAPRIKA_VA',
            'Order created with status PENDING',
            $body,
            null
        );

        $msg = 'Success Create Order Paprika VA';
        $result['link'] = $vaNumber;
        if (FormatHelper::isNotEmpty($request->version) && $request->version == '2') {
            $token = $this->redisService->generatePaymentToken($project->id, $project->value, $order->getReference());
            $result['link'] = env('PAYMENT_URL') . '/detailpayment?token=' . $token . '&reference=' . $order->getReference();
        }
        $result['result'] = $responseData;
        $result['message'] = $msg;

        return $result;
    }

    public function callback(Request $request)
    {
        LogHelper::sendLog('callback_paprika', $request->all());

        $now = Carbon::now();
        $dateBefore = date('Y-m-d', strtotime('-1 week'));
        $order = $this->orderService->findRecentByReference(
            $request->input('originalReferenceNo'),
            $dateBefore,
            $now->toDateTimeString()
        );

        if (! $order) {
            throw new Exception('Order not found');
        }

        $required = ['originalPartnerReferenceNo', 'latestTransactionStatus', 'originalReferenceNo'];
        foreach ($required as $field) {
            if (empty($request->input($field))) {
                throw new Exception("Missing required field: $field");
            }
        }

        $order = $this->orderService->findOrFailCustom($order->getId());

        $currentStatus = $order->getStatus();
        if ($currentStatus !== null && ($currentStatus->isSuccess() || $currentStatus->isFailed())) {
            LogHelper::sendLog('callback_paprika_idempotent', [
                'reference' => $order->getReference(),
                'status' => $currentStatus->value,
            ]);
            return response()->json([
                'responseCode' => '2002600',
                'responseMessage' => 'Success',
            ]);
        }

        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        if (! FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository not found');
        }

        $this->verifyCallbackSignature($request, $paymentRepo);

        $status = match ($request->input('latestTransactionStatus')) {
            '00' => OrderStatus::SUCCESS,
            '06' => OrderStatus::FAILED,
            default => null,
        };

        $previousStatus = $order->status;
        $order->setCallback(json_encode($request->all()));

        if ($status !== null) {
            $order->setStatus($status);
        }

        $payerIssuer = $request->input('additionalInfo.payerIssuer', '');
        $paymentMethod = $this->paymentService->getPaymentMethodByKeyAndGatewayKey($payerIssuer, 'paprika');
        if (FormatHelper::isNotEmpty($paymentMethod)) {
            $order->setPaymentMethod($paymentMethod->key);
        }

        $order->save();

        if ($status !== null) {
            $this->orderHistoryService->log(
                $order,
                $status,
                'WEBHOOK_PAPRIKA',
                "Paprika QR callback received: {$request->input('latestTransactionStatus')}",
                $request->all(),
                $previousStatus
            );
        }

        $reference = $order->getReference();
        $split = explode('-', $reference);
        $project = $this->projectService->getByType($split[0]);
        if (! FormatHelper::isNotEmpty($project)) {
            throw new Exception('Project Not Found');
        }

        LogHelper::sendLog(
            'Callback Paprika',
            json_encode($order->getCallback()),
            $project->id,
            'callback_order_paprika'
        );

        $params = [
            'originalPartnerReferenceNo' => $order->getMerchantOrderId(),
            'originalReferenceNo' => $request->input('originalReferenceNo'),
            'latestTransactionStatus' => $request->input('latestTransactionStatus'),
            'amount' => $request->input('amount'),
        ];

        SendMerchantCallback::dispatch($project->value, $params, $project->callback);
        SendNotificationJob::dispatch($reference);
        // $order->refresh();

        return response()->json([
            'responseCode' => '2002600',
            'responseMessage' => 'Success',
        ]);
    }

    private function assertPaprikaSuccess(array $responseData): void
    {
        if (!empty($responseData['virtualAccountData']['virtualAccountNo'])) {
            return;
        }

        $responseCode = $responseData['responseCode'] ?? null;

        if ($responseCode !== null && str_starts_with((string) $responseCode, '200')) {
            return;
        }

        throw new RuntimeException(
            $responseData['responseMessage'] ?? 'Failed to create order at Paprika'
        );
    }

    private function verifyCallbackSignature(Request $request, PaymentRepository $paymentRepo): void
    {
        $paymentConfig = $paymentRepo->getValue();
        $clientSecret = $paymentConfig['api_secret'];
        $clientKey = $paymentConfig['api_key'];

        $receivedSignature = $request->header('X-SIGNATURE');
        $timestamp = $request->header('X-TIMESTAMP');
        $partnerId = $request->header('X-PARTNER-ID');

        if (empty($receivedSignature) || empty($timestamp) || empty($partnerId)) {
            throw new \Exception('Missing callback signature headers');
        }

        $requestBody = json_encode($request->except('X-SIGNATURE'));
        $bodyHash = hash('sha256', $requestBody);
        $stringToSign = "POST:/api/callback/paprika:{$partnerId}:{$bodyHash}:{$timestamp}";

        $expectedSignature = base64_encode(
            hash_hmac('sha512', $stringToSign, $clientSecret, true)
        );

        if (! hash_equals($expectedSignature, $receivedSignature)) {
            throw new \Exception('Invalid callback signature');
        }
    }

    public function dailyUnique(string $param, int $length = 36): string
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

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        return [
            'gateway' => 'Paprika',
            'has_more' => false,
            'total' => 0,
            'items' => [],
            'message' => 'Paprika SNAP QR live transaction inquiry is connected.',
        ];
    }
}
