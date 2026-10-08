<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Exceptions\BankAgiException;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Interface\RedisServiceInterface;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Repository\Payment\OrderRepository;
use App\Services\Network\NetworkService;
use App\Services\System\RedisService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class BankAgiService
{
    private const TOKEN_PATH = '/api/v1/bisnap/access-token';

    private const GENERATE_PATH = '/snap/api/v1.0/qr/qr-mpm-generate';

    private const QUERY_PATH = '/snap/api/v1.0/qr/qr-mpm-query';

    private const TOKEN_TTL = 900;

    private const MAX_BODY_BYTES = 65536;

    private PaymentRepositoryService $paymentRepositoryService;

    private RedisServiceInterface $redisService;

    private NetworkService $networkService;

    private OrderService $orderService;

    private OrderRepository $orders;

    private OrderHistoryService $orderHistoryService;

    public function __construct(
        ?PaymentRepositoryService $paymentRepositoryService = null,
        ?RedisServiceInterface $redisService = null,
        ?NetworkService $networkService = null,
        ?OrderService $orderService = null,
        ?OrderRepository $orders = null,
        ?OrderHistoryService $orderHistoryService = null,
    ) {
        $this->paymentRepositoryService = $paymentRepositoryService ?? new PaymentRepositoryService;
        $this->redisService = $redisService ?? new RedisService;
        $this->networkService = $networkService ?? new NetworkService;
        $this->orderService = $orderService ?? new OrderService;
        $this->orders = $orders ?? new OrderRepository;
        $this->orderHistoryService = $orderHistoryService ?? new OrderHistoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): PaymentRepository
    {
        $mode = PaymentModeType::fromName($mode);
        $repository = $id !== null && $id !== ''
            ? $this->paymentRepositoryService->getById($id)
            : $this->paymentRepositoryService->getByPaymentGatewayKey('bank_agi', ($mode ?? PaymentModeType::sandbox)->value);

        if (! $repository || $repository->getRelationValue('payment_gateway')?->getKey() !== 'bank_agi') {
            throw new RuntimeException('AGI Payment Repository Not Found');
        }

        if ($mode !== null && $repository->getMode() !== $mode) {
            throw new RuntimeException('AGI payment repository mode does not match the requested mode');
        }

        return $repository;
    }

    public function generateB2BAccessToken(Request $request): array
    {
        $clientId = $this->requiredHeader($request, 'X_CLIENT_KEY', '73');
        $timestamp = $this->requiredHeader($request, 'X_TIMESTAMP', '73');
        $signature = $this->requiredHeader($request, 'X_SIGNATURE', '73');
        $this->validateTimestamp($timestamp, '73');

        $repository = $this->paymentRepositoryService->getByGatewayKeyAndClientKey('bank_agi', $clientId);
        if (! $repository) {
            throw new BankAgiException('4017300', 'Unauthorized [Unknown Client Key]', 401);
        }

        $config = $repository->getValue();
        if ($clientId !== ($config['bank_client_id'] ?? $config['client_id'] ?? null)) {
            throw new BankAgiException('4017300', 'Unauthorized [Invalid Client Key]', 401);
        }

        $publicKey = openssl_pkey_get_public(str_replace('\\n', "\n", $config['bank_public_key'] ?? ''));
        $decodedSignature = base64_decode($signature, true);
        if ($publicKey === false || $decodedSignature === false || openssl_verify($clientId.'|'.$timestamp, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new BankAgiException('4017300', 'Unauthorized [Signature]', 401);
        }

        $token = bin2hex(random_bytes(32));
        if (! $this->redisService->set($this->tokenKey($token), [
            'repository_id' => $repository->getAttribute('id'),
            'client_key' => $clientId,
        ], self::TOKEN_TTL)) {
            throw new RuntimeException('Unable to store AGI access token');
        }

        return [
            'responseCode' => '2007300',
            'responseMessage' => 'Successful',
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'expiresIn' => (string) self::TOKEN_TTL,
        ];
    }

    public function validatePayload(Request $request, string $serviceCode): void
    {
        $body = $request->getContent();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new BankAgiException('413'.$serviceCode.'00', 'Payload Too Large', 413);
        }

        if (! $request->isJson() || ! json_validate($body)) {
            throw new BankAgiException('400'.$serviceCode.'01', 'Invalid Field Format [body]', 400);
        }
    }

    public function signAsymmetric(string $clientId, string $timestamp, string $privateKey): string
    {
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", $privateKey));
        if ($key === false || ! openssl_sign($clientId.'|'.$timestamp, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign AGI access token request');
        }

        return base64_encode($signature);
    }

    public function signSymmetric(string $secret, string $method, string $path, string $token, string $body, string $timestamp): string
    {
        $decodedSecret = base64_decode($secret, true);
        if ($decodedSecret === false || $decodedSecret === '') {
            throw new RuntimeException('AGI client secret must be a non-empty Base64 string');
        }

        $hash = hash('sha256', $this->minifyBody($body));
        $stringToSign = strtoupper($method).':'.$path.':'.$token.':'.$hash.':'.$timestamp;

        return hash_hmac('sha512', $stringToSign, $decodedSecret);
    }

    public function getB2BToken(PaymentRepository $repository): array
    {
        $config = $repository->getValue();
        $clientId = $this->requiredConfig($config, 'client_id');
        $privateKey = $this->requiredConfig($config, 'private_key');
        $url = $this->baseUrl($repository);
        $cacheKey = 'bank_agi:outbound_token:'.hash('sha256', json_encode([
            $repository->getAttribute('id'), $url, $clientId, $privateKey, $config['client_secret'] ?? null,
        ], JSON_THROW_ON_ERROR));
        try {
            $cached = $this->redisService->get($cacheKey);
            if (is_array($cached) && ($cached['expires_at'] ?? 0) > now()->getTimestamp()
                && is_string($cached['response']['accessToken'] ?? null) && $cached['response']['accessToken'] !== '') {
                return $cached['response'];
            }
        } catch (\Throwable) {
        }

        $requestedAt = now()->getTimestamp();
        $timestamp = $this->timestamp();
        $headers = [
            'Content-Type' => 'application/json',
            'X_CLIENT_KEY' => $clientId,
            'X_TIMESTAMP' => $timestamp,
            'X_SIGNATURE' => $this->signAsymmetric($clientId, $timestamp, $privateKey),
        ];

        $response = $this->decodeResponse($this->networkService->post(
            $url.self::TOKEN_PATH,
            $headers,
            ['grantType' => 'client_credentials', 'additionalInfo' => (object) []],
            ['allow_redirects' => false],
        ), '2007300', ['endpoint' => self::TOKEN_PATH]);

        if (empty($response['accessToken']) || ! is_string($response['accessToken'])) {
            throw new RuntimeException('AGI access token response is missing accessToken');
        }

        $expiresIn = $response['expiresIn'] ?? '';
        $expiresIn = is_string($expiresIn) || is_int($expiresIn) ? (string) $expiresIn : '';
        $ttl = ctype_digit($expiresIn) ? min(60, max(0, (int) $expiresIn - 5 - (now()->getTimestamp() - $requestedAt))) : 0;
        if ($ttl > 0) {
            try {
                $this->redisService->set($cacheKey, ['response' => $response, 'expires_at' => now()->getTimestamp() + $ttl], $ttl);
            } catch (\Throwable) {
            }
        }

        return $response;
    }

    public function order(Request $request, Project $project, ?Order $existingOrder = null): array
    {
        if ($existingOrder !== null) {
            $existingOrder = $this->orders->findByIdForUpdate($existingOrder->getId());
            if (! $existingOrder) {
                throw new RuntimeException('AGI order not found');
            }

            if ($existingOrder->getType() !== $project->getType() || $existingOrder->getStatus() !== OrderStatus::PENDING || $existingOrder->getValue()) {
                throw new RuntimeException('AGI payment can only be created for an unpaid order without a generated QRIS');
            }

            $request->merge([
                'merchantOrderId' => $existingOrder->getMerchantOrderId(),
                'paymentAmount' => number_format($existingOrder->getAmount(), 2, '.', ''),
                'mode' => $existingOrder->getMode()?->value,
                'paymentRepositoryId' => $request->input('paymentRepositoryId', $existingOrder->getAttribute('payment_repository_id')),
            ]);
        }

        $request->validate([
            'merchantOrderId' => ['required', 'string', 'max:100'],
            'paymentAmount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999999.99'],
            'paymentMethod' => ['required', 'string'],
            'paymentRepositoryId' => ['sometimes', 'nullable', 'string', 'uuid'],
            'mode' => ['sometimes', 'nullable', 'in:sandbox,prod'],
            'expiryPeriod' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        if (! in_array(strtolower($request->input('paymentMethod')), ['qris', 'agi_qris'], true)) {
            throw new RuntimeException('AGI only supports QRIS with the supplied documentation');
        }

        $paymentUrl = config('app.payment_url');
        if ((string) $request->input('version') === '2' && (! is_string($paymentUrl) || $paymentUrl === '')) {
            throw new RuntimeException('PAYMENT_URL is required for AGI checkout version 2');
        }

        $repository = $this->getPaymentRepo($request->input('mode'), $request->input('paymentRepositoryId'));
        $config = $repository->getValue();
        $reference = $existingOrder?->getReference() ?? $project->getType().'-'.$request->input('merchantOrderId');
        $validity = (int) $request->input('expiryPeriod', $config['validity_period'] ?? 60);
        if ($validity < 1 || $validity > 1440) {
            throw new RuntimeException('AGI validity period must be between 1 and 1440 minutes');
        }

        $amount = $this->formatAmount($request->input('paymentAmount'));
        $body = [
            'partnerReferenceNo' => $reference,
            'amount' => ['value' => $amount, 'currency' => 'IDR'],
            'merchantId' => $this->requiredConfig($config, 'merchant_id'),
            'validityPeriod' => (string) $validity,
            'additionalInfo' => $this->additionalInfo($config),
        ];

        foreach (['sub_merchant_id' => 'subMerchantId', 'store_id' => 'storeId', 'terminal_id' => 'terminalId'] as $key => $field) {
            if (! empty($config[$key])) {
                $body[$field] = (string) $config[$key];
            }
        }

        $order = $existingOrder ?? $this->orderService->createAndFind([
            'id' => OrderIdGenerator::generate(),
            'reference' => $reference,
            'type' => $project->getType(),
            'mode' => $repository->getMode()->value,
            'payment_repository_id' => $repository->getAttribute('id'),
            'payment_method' => 'AGI_QRIS',
            'amount' => $amount,
            'name' => trim($request->input('firstName', '').' '.$request->input('lastName', '')) ?: $request->input('name'),
            'return_url' => $request->input('returnUrl', $request->input('return_url', $project->getCallback())),
            'expired_at' => now()->addMinutes($validity),
            'request' => json_encode($body, JSON_THROW_ON_ERROR),
            'status' => OrderStatus::PENDING->value,
        ]);

        LogHelper::sendLog('Request Order AGI', $body, (string) $project->getAttribute('id'), 'request_order_agi');
        $response = $this->sendTransaction($repository, self::GENERATE_PATH, $body, '2001700');
        if (! is_string($response['qrContent'] ?? null) || $response['qrContent'] === ''
            || ! is_string($response['additionalInfo']['billNumber'] ?? null) || $response['additionalInfo']['billNumber'] === '') {
            throw new RuntimeException('AGI QRIS response is missing qrContent or billNumber');
        }

        $order->setRequest(json_encode($body, JSON_THROW_ON_ERROR));
        $order->setResponse(json_encode($response, JSON_THROW_ON_ERROR));
        $order->setPaymentRepositoryId($repository->getAttribute('id'));
        $order->setPaymentMethod('AGI_QRIS');
        $order->setGatewayReference($response['referenceNo'] ?? null);
        $order->setGatewayBillNumber((string) $response['additionalInfo']['billNumber']);
        $order->setValue($response['qrContent']);
        $order->setUrl($response['qrContent']);
        $order->setExpiredAt(now()->addMinutes($validity));
        $expiryTime = $response['additionalInfo']['expiryTime'] ?? null;
        if (is_string($expiryTime) && preg_match('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $expiryTime)) {
            $order->setExpiredAt(Carbon::createFromFormat('d-m-Y H:i:s', $expiryTime, 'Asia/Jakarta')->utc());
        }
        $order->save();

        $this->orderHistoryService->log($order, OrderStatus::PENDING, 'ORDER_CREATE_AGI', 'AGI QRIS created', $body);
        LogHelper::sendLog('Response Order AGI', $response, (string) $project->getAttribute('id'), 'response_order_agi');

        $link = $response['qrContent'];
        if ((string) $request->input('version') === '2') {
            $token = $this->redisService->generatePaymentToken((int) $project->getAttribute('id'), $project->getValue(), $reference);
            $link = rtrim($paymentUrl, '/').'/detailpayment?'.http_build_query([
                'token' => $token,
                'reference' => $reference,
            ]);
        }

        return [
            'reference' => $reference,
            'link' => $link,
            'qr_string' => $response['qrContent'],
            'result' => $response,
            'message' => 'Success Create Order AGI QRIS',
        ];
    }

    public function checkStatus(Order $order): array
    {
        $repository = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        $config = $repository->getValue();
        if (! $order->getGatewayBillNumber()) {
            throw new RuntimeException('AGI QRIS bill number is missing from the order');
        }

        $body = [
            'originalReferenceNo' => $order->getGatewayReference() ?? $order->getReference(),
            'originalPartnerReferenceNo' => $order->getReference(),
            'serviceCode' => '17',
            'merchantId' => $this->requiredConfig($config, 'merchant_id'),
            'additionalInfo' => array_merge($this->additionalInfo($config), ['billNumber' => $order->getGatewayBillNumber()]),
        ];
        if (! empty($config['sub_merchant_id'])) {
            $body['subMerchantId'] = (string) $config['sub_merchant_id'];
        }

        return $this->sendTransaction($repository, self::QUERY_PATH, $body, '2001800');
    }

    public function callback(Request $request): array
    {
        $repository = $this->authenticateCallback($request);
        $order = $this->orders->findByGatewayBillNumber(
            $repository->getAttribute('id'),
            (string) $request->input('additionalInfo.billNumber'),
        );
        if (! $order) {
            throw new BankAgiException('4041901', 'Transaction Not Found', 404);
        }

        $originalRequest = json_decode((string) $order->getRequest(), true, 512, JSON_THROW_ON_ERROR);
        if ($request->input('additionalInfo.merchantId') !== ($originalRequest['merchantId'] ?? null)
            || $request->input('additionalInfo.merchantUser') !== ($originalRequest['additionalInfo']['merchantUser'] ?? null)) {
            throw new BankAgiException('4011900', 'Unauthorized [Merchant]', 401);
        }

        if ($request->has('amount') && ($this->formatAmount($request->input('amount.value')) !== ($originalRequest['amount']['value'] ?? null) || $request->input('amount.currency') !== 'IDR')) {
            throw new BankAgiException('4001901', 'Invalid Field Format [amount]', 400);
        }

        $previousStatus = $order->getStatus();
        $status = match ($request->input('latestTransactionStatus')) {
            '00' => OrderStatus::SUCCESS,
            '05', '06' => OrderStatus::FAILED,
            '01', '02', '03' => OrderStatus::PENDING,
            default => null,
        };

        if ($previousStatus === OrderStatus::SUCCESS || $status === null || $status === $previousStatus
            || ($status === OrderStatus::PENDING && $previousStatus?->isFailed())) {
            return ['responseCode' => '2001900', 'responseMessage' => 'Success'];
        }

        $order->setCallback($request->getContent());
        $order->setStatus($status);
        $order->save();
        $this->orderHistoryService->log($order, $status, 'WEBHOOK_AGI', 'AGI notification received', $request->all(), $previousStatus);

        $project = $order->getRelationValue('project');
        if ($project && $project->getCallback()) {
            dispatch(new SendMerchantCallback($project->getValue(), [
                'merchantOrderId' => $order->getMerchantOrderId(),
                'reference' => $order->getReference(),
                'paymentCode' => $order->getPaymentMethod(),
                'amount' => $order->getAmount(),
                'status' => $status->value,
                'resultCode' => $status === OrderStatus::SUCCESS ? '00' : '01',
            ], $project->getCallback()))->afterCommit();
        }
        dispatch(new SendNotificationJob($order->getReference()))->afterCommit();

        return ['responseCode' => '2001900', 'responseMessage' => 'Success'];
    }

    private function authenticateCallback(Request $request): PaymentRepository
    {
        $partnerId = $this->requiredHeader($request, 'X_PARTNER_ID', '19');
        $timestamp = $this->requiredHeader($request, 'X_TIMESTAMP', '19');
        $signature = $this->requiredHeader($request, 'X_SIGNATURE', '19');
        $externalId = $this->requiredHeader($request, 'X_EXTERNAL_ID', '19');
        $channelId = $this->requiredHeader($request, 'CHANNEL_ID', '19');
        $this->validateTimestamp($timestamp, '19');
        if (! preg_match('/^\d{1,36}$/', $externalId) || ! preg_match('/^\d{5}$/', $channelId)) {
            throw new BankAgiException('4001901', 'Invalid Field Format [X_EXTERNAL_ID or CHANNEL_ID]', 400);
        }

        $token = $request->bearerToken();
        if (! $token) {
            throw new BankAgiException('4011903', 'Token Not Found (B2B)', 401);
        }
        if (strlen($token) > 128) {
            throw new BankAgiException('4011901', 'Invalid Token (B2B)', 401);
        }

        $storedToken = $this->redisService->get($this->tokenKey($token));
        if (! is_array($storedToken) || ($storedToken['client_key'] ?? null) !== $partnerId || empty($storedToken['repository_id'])) {
            throw new BankAgiException('4011901', 'Invalid Token (B2B)', 401);
        }

        $repository = $this->getPaymentRepo(null, $storedToken['repository_id']);
        $config = $repository->getValue();
        if ($partnerId !== ($config['bank_client_id'] ?? $config['client_id'] ?? null)) {
            throw new BankAgiException('4011900', 'Unauthorized [Partner ID]', 401);
        }

        $secret = $config['bank_client_secret'] ?? $this->requiredConfig($config, 'client_secret');
        $expected = $this->signSymmetric($secret, $request->method(), $request->getPathInfo(), $token, $request->getContent(), $timestamp);
        if (! hash_equals($expected, $signature)) {
            throw new BankAgiException('4011900', 'Unauthorized [Signature]', 401);
        }

        return $repository;
    }

    private function sendTransaction(PaymentRepository $repository, string $path, array $body, string $successCode): array
    {
        $config = $repository->getValue();
        $channelId = $this->requiredConfig($config, 'channel_id');
        if (! preg_match('/^\d{5}$/', $channelId)) {
            throw new RuntimeException('AGI channel_id must contain exactly five digits');
        }

        $token = $this->getB2BToken($repository)['accessToken'];
        $timestamp = $this->timestamp();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$token,
            'X_TIMESTAMP' => $timestamp,
            'X_PARTNER_ID' => $this->requiredConfig($config, 'client_id'),
            'X_EXTERNAL_ID' => now('UTC')->format('YmdHis').sprintf('%018d', random_int(0, 999999999999999999)),
            'CHANNEL_ID' => $channelId,
            'X_SIGNATURE' => $this->signSymmetric($this->requiredConfig($config, 'client_secret'), 'POST', $path, $token, json_encode($body, JSON_THROW_ON_ERROR), $timestamp),
        ];

        return $this->decodeResponse($this->networkService->post($this->baseUrl($repository).$path, $headers, $body, ['allow_redirects' => false]), $successCode, [
            'endpoint' => $path,
            'external_id' => $headers['X_EXTERNAL_ID'],
            'repository_id' => $repository->getAttribute('id'),
        ]);
    }

    private function decodeResponse(?string $rawResponse, string $successCode, array $context = []): array
    {
        try {
            $response = json_decode((string) $rawResponse, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            Log::error('AGI invalid JSON response', $context + ['expected_code' => $successCode, 'response_bytes' => strlen((string) $rawResponse)]);
            throw new RuntimeException('AGI returned an invalid JSON response', 0, $ex);
        }

        if (! is_array($response) || ($response['responseCode'] ?? null) !== $successCode) {
            $code = is_array($response) && is_string($response['responseCode'] ?? null) ? $response['responseCode'] : null;
            $message = is_array($response) && is_string($response['responseMessage'] ?? null) ? $response['responseMessage'] : 'Invalid response';
            Log::error('AGI request failed', $context + [
                'expected_code' => $successCode,
                'response_code' => $code,
                'response_message' => $message,
            ]);
            throw new RuntimeException('AGI request failed: '.$message);
        }

        return $response;
    }

    private function additionalInfo(array $config): array
    {
        $info = ['merchantUser' => $this->requiredConfig($config, 'merchant_user')];
        foreach (['device_id' => 'deviceId', 'channel' => 'channel'] as $key => $field) {
            if (! empty($config[$key])) {
                $info[$field] = (string) $config[$key];
            }
        }

        return $info;
    }

    private function baseUrl(PaymentRepository $repository): string
    {
        $url = rtrim($this->requiredConfig($repository->getValue(), 'base_url'), '/');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! parse_url($url, PHP_URL_HOST)
            || parse_url($url, PHP_URL_PATH) || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new RuntimeException('AGI base_url must be an HTTPS origin without a path or query');
        }
        if (! in_array(strtolower(parse_url($url, PHP_URL_HOST)), config('services.bank_agi.allowed_hosts', []), true)) {
            throw new RuntimeException('AGI base_url host is not in BANK_AGI_ALLOWED_HOSTS');
        }

        return $url;
    }

    private function requiredConfig(array $config, string $key): string
    {
        if (! isset($config[$key]) || ! is_string($config[$key]) || trim($config[$key]) === '') {
            throw new RuntimeException('AGI '.$key.' is not configured');
        }

        return $config[$key];
    }

    private function formatAmount(mixed $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim((string) $amount, '+'), 2), 2, '');

        return (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
    }

    private function requiredHeader(Request $request, string $header, string $serviceCode): string
    {
        $value = $request->header($header, $request->header(str_replace('_', '-', $header)));
        if (! is_string($value) || trim($value) === '') {
            throw new BankAgiException('400'.$serviceCode.'02', 'Invalid Mandatory Field ['.$header.']', 400);
        }

        $maxLength = match ($header) {
            'X_TIMESTAMP' => 35,
            'X_SIGNATURE' => 2048,
            'X_EXTERNAL_ID' => 36,
            'CHANNEL_ID' => 5,
            default => 128,
        };
        if (strlen($value) > $maxLength) {
            throw new BankAgiException('400'.$serviceCode.'01', 'Invalid Field Format ['.$header.']', 400);
        }

        return $value;
    }

    private function validateTimestamp(string $timestamp, string $serviceCode): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/', $timestamp)) {
            throw new BankAgiException('400'.$serviceCode.'01', 'Invalid Field Format [X_TIMESTAMP]', 400);
        }
        if (! checkdate((int) substr($timestamp, 5, 2), (int) substr($timestamp, 8, 2), (int) substr($timestamp, 0, 4))) {
            throw new BankAgiException('400'.$serviceCode.'01', 'Invalid Field Format [X_TIMESTAMP]', 400);
        }

        try {
            $parsed = Carbon::parse($timestamp);
        } catch (\Throwable) {
            throw new BankAgiException('400'.$serviceCode.'01', 'Invalid Field Format [X_TIMESTAMP]', 400);
        }

        if (abs(now()->diffInSeconds($parsed, false)) > 300) {
            throw new BankAgiException('401'.$serviceCode.'00', 'Unauthorized [Timestamp expired]', 401);
        }
    }

    private function minifyBody(string $body): string
    {
        if ($body === '') {
            return '';
        }

        if (! json_validate($body)) {
            throw new RuntimeException('AGI signature body must be valid JSON');
        }
        $minified = '';
        $quoted = false;
        $escaped = false;
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $character = $body[$index];
            if ($quoted) {
                $minified .= $character;
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $quoted = false;
                }
            } elseif ($character === '"') {
                $quoted = true;
                $minified .= $character;
            } elseif (! str_contains(" \t\r\n", $character)) {
                $minified .= $character;
            }
        }

        return $minified;
    }

    private function tokenKey(string $token): string
    {
        return 'agi:snap_token:'.hash('sha256', $token);
    }

    private function timestamp(): string
    {
        return now('UTC')->format('Y-m-d\TH:i:s.vP');
    }
}
