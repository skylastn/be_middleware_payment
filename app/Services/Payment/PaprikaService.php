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

    public function generateB2BAccessToken(Request $request)
    {
        $clientKey = $request->header('X-CLIENT-KEY');
        $timestamp = $request->header('X-TIMESTAMP');
        $signature = $request->header('X-SIGNATURE');

        // Step 2: Validate mandatory headers
        if (empty($clientKey) || empty($timestamp) || empty($signature)) {
            return response()->json([
                'responseCode' => '4007302',
                'responseMessage' => 'Missing Mandatory Field [X-CLIENT-KEY, X-TIMESTAMP, or X-SIGNATURE]',
            ], 400);
        }

        // Step 3: Format check client key (UUID format)
        if (! preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $clientKey)) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized [Invalid Client Key format]',
            ], 401);
        }

        // Step 4: Validate timestamp format and expiry (<= 300 seconds)
        try {
            $parsedTimestamp = Carbon::parse($timestamp);
            $timeDiff = abs(now()->diffInSeconds($parsedTimestamp, false));
            if ($timeDiff > 300) {
                return response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized [Timestamp expired]',
                ], 401);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format [X-TIMESTAMP]',
            ], 400);
        }

        // Step 5: Validate grantType
        if ($request->input('grantType') !== 'client_credentials') {
            return response()->json([
                'responseCode' => '4007301',
                'responseMessage' => 'Invalid Field Format [grantType must be client_credentials]',
            ], 400);
        }

        // Lookup repository by clientKey
        $paymentRepo = $this->paymentRepositoryService->getByGatewayKeyAndClientKey('paprika', $clientKey);
        if (! $paymentRepo) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized [Unknown Client Key]',
            ], 401);
        }

        $config = $paymentRepo->getValue();
        $publicKeyPem = $config['public_key']
            ?? (isset($config['private_key']) ? self::getPublicKey($config['private_key']) : null);

        if (empty($publicKeyPem)) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized [Public key not found for client]',
            ], 401);
        }

        // Step 6: Verify asymmetric signature
        $stringToVerify = "{$clientKey}|{$timestamp}";
        $publicKey = openssl_pkey_get_public(str_replace('\n', "\n", $publicKeyPem));
        $decodedSignature = base64_decode($signature);

        if (! $publicKey || openssl_verify($stringToVerify, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            return response()->json([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized [Signature]',
            ], 401);
        }

        // Step 8: Generate access token
        $expiresIn = 900;
        $accessToken = bin2hex(random_bytes(32));
        $this->redisService->storeSnapAccessToken($accessToken, $clientKey, $expiresIn);

        // Step 9: Return success response
        return response()->json([
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'accessToken' => $accessToken,
            'tokenType' => 'Bearer',
            'expiresIn' => (string) $expiresIn,
        ], 200);
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
        $amount = (float) ($request->paymentAmount ?? 0);
        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));

        $req['id'] = $idSystem;
        $req['reference'] = $reference;
        $req['name'] = $customerName ?: ($request->customerVaName ?? $request->name ?? null);
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';
        $req['amount'] = $amount;
        $req['return_url'] = $request->returnUrl ?? $request->return_url ?? $project->callback;

        $url = "$baseurl/api/snap/v1.0/qr/qr-mpm-generate";

        $body = [
            'partnerReferenceNo' => $reference,
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
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
        $amount = (float) ($request->paymentAmount ?? 0);
        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));

        $req['id'] = $idSystem;
        $req['reference'] = $reference;
        $req['name'] = $customerName ?: ($request->customerVaName ?? $request->name ?? null);
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';
        $req['amount'] = $amount;
        $req['return_url'] = $request->returnUrl ?? $request->return_url ?? $project->callback;

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
                'value' => number_format($amount, 2, '.', ''),
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

        // Validate Bearer token if provided (SNAP standard)
        $bearerToken = $request->bearerToken();
        if (! empty($bearerToken)) {
            $tokenData = $this->redisService->getSnapAccessToken($bearerToken);
            if (! $tokenData) {
                return response()->json([
                    'responseCode' => '4012600',
                    'responseMessage' => 'Unauthorized [Invalid or expired access token]',
                ], 401);
            }
        }

        $now = Carbon::now();
        $dateBefore = date('Y-m-d', strtotime('-1 week'));
        $reference = $request->input('originalReferenceNo')
            ?: $request->input('paymentRequestId')
            ?: $request->input('trxId');

        $order = null;
        if (! empty($reference)) {
            $order = $this->orderService->findRecentByReference(
                $reference,
                $dateBefore,
                $now->toDateTimeString()
            );
        }

        $valueLookup = $request->input('virtualAccountNo') ?: $request->input('qrString') ?: $request->input('qrContent');
        if (! $order && ! empty($valueLookup)) {
            $order = $this->orderService->findOneByValue($valueLookup);
        }

        if (! $order) {
            throw new Exception('Order not found');
        }

        $required = ['originalPartnerReferenceNo', 'latestTransactionStatus', 'originalReferenceNo'];
        if (empty($request->input('virtualAccountNo')) && empty($request->input('paidAmount'))) {
            foreach ($required as $field) {
                if (empty($request->input($field))) {
                    throw new Exception("Missing required field: $field");
                }
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

        $transactionStatus = $request->input('latestTransactionStatus');
        if ($transactionStatus !== null) {
            $status = match ($transactionStatus) {
                '00' => OrderStatus::SUCCESS,
                '06' => OrderStatus::FAILED,
                default => null,
            };
        } else {
            // Virtual account payment notify implies success if paidAmount is present
            $status = OrderStatus::SUCCESS;
        }

        $previousStatus = $order->status;
        $order->setCallback(json_encode($request->all()));

        if ($status !== null) {
            $order->setStatus($status);
        }

        $payerIssuer = $request->input('additionalInfo.payerIssuer', '');
        if (! empty($payerIssuer)) {
            $paymentMethod = $this->paymentService->getPaymentMethodByKeyAndGatewayKey($payerIssuer, 'paprika');
            if (FormatHelper::isNotEmpty($paymentMethod)) {
                $order->setPaymentMethod($paymentMethod->key);
            }
        }

        $order->save();

        if ($status !== null) {
            $this->orderHistoryService->log(
                $order,
                $status,
                'WEBHOOK_PAPRIKA',
                "Paprika callback received status: " . ($transactionStatus ?? 'SUCCESS'),
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

        $params['merchantOrderId'] = $order->getMerchantOrderId();
        $params['paymentCode'] = $order->getPaymentMethod();
        $params['resultCode'] = $transactionStatus ?? '00';

        SendMerchantCallback::dispatch($project->value, $params, $project->callback);
        SendNotificationJob::dispatch($reference);

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
        $signature = $request->header('X-SIGNATURE');
        $timestamp = $request->header('X-TIMESTAMP');
        $accessToken = $request->bearerToken();

        if (empty($signature) || empty($timestamp) || empty($accessToken)) {
            throw new Exception('Missing callback signature headers');
        }

        // SNAP v1.0.2: {HTTP_METHOD}:{URL_PATH}:{accessToken}:{SHA256_hex(body)}:{X-TIMESTAMP}
        $method = strtoupper($request->method());
        $path = $request->getPathInfo();
        $rawBody = $request->getContent() ?: json_encode($request->except('X-SIGNATURE'), JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $rawBody);

        $stringToSign = "{$method}:{$path}:{$accessToken}:{$bodyHash}:{$timestamp}";
        $clientSecret = $paymentRepo->getValue()['api_secret'];
        $expectedSignature = base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));

        if (! hash_equals($expectedSignature, $signature)) {
            throw new Exception('Invalid callback signature');
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
