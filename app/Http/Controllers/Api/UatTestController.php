<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectSlug;
use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Repository\Payment\OrderRepository;
use App\Repository\Payment\PaymentRepositoryRepository;
use App\Services\Payment\BankAgiService;
use App\Services\Payment\PaymentRepositoryService;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class UatTestController extends Controller
{
    private BankAgiService $bankAgiService;
    private PaymentRepositoryService $repositoryService;
    private PaymentRepositoryRepository $repositories;
    private OrderRepository $orders;
    private ProjectService $projectService;

    public function __construct(
        ?BankAgiService $bankAgiService = null,
        ?PaymentRepositoryService $repositoryService = null,
        ?PaymentRepositoryRepository $repositories = null,
        ?OrderRepository $orders = null,
        ?ProjectService $projectService = null,
    ) {
        $this->bankAgiService = $bankAgiService ?? new BankAgiService;
        $this->repositoryService = $repositoryService ?? new PaymentRepositoryService;
        $this->repositories = $repositories ?? new PaymentRepositoryRepository;
        $this->orders = $orders ?? new OrderRepository;
        $this->projectService = $projectService ?? new ProjectService;
    }

    public function repositories(Request $request, string $gateway): JsonResponse
    {
        if ($gateway !== ProjectSlug::BANK_AGI->value) {
            return ResponseHelper::failedResponse('Unsupported UAT gateway', 'Not Found', 404);
        }

        $rows = $this->repositories->latestPaginated(100, null, $request->query('mode'))
            ->getCollection()
            ->filter(fn (PaymentRepository $r) => $r->payment_gateway?->key === $gateway)
            ->values()
            ->map(fn (PaymentRepository $r) => [
                'id' => (string) $r->getAttribute('id'),
                'key' => $r->getAttribute('key'),
                'mode' => $r->getMode()?->value,
                'gateway_name' => $r->payment_gateway?->name,
                'updated_at' => (string) $r->getAttribute('updated_at'),
            ]);

        return ResponseHelper::successResponse($rows);
    }

    public function generateQr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'repositoryId' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'expiryPeriod' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        try {
            DB::beginTransaction();
            $repository = $this->requireRepository($data['repositoryId']);
            LogHelper::sendLog('UAT Generate QR - Request', [
                'repository_id' => $repository->getAttribute('id'),
                'repository_key' => $repository->getAttribute('key'),
                'mode' => $repository->getMode()?->value,
                'amount' => $data['amount'],
                'expiry_period' => $data['expiryPeriod'] ?? 60,
            ], '', 'uat_generate_qr');

            $response = $this->bankAgiService->order($this->uatOrderRequest($repository, $data), $this->uatProject($repository));
            DB::commit();

            LogHelper::sendLog('UAT Generate QR - Success', [
                'reference' => $response['reference'] ?? null,
                'bill_number' => $response['result']['additionalInfo']['billNumber'] ?? null,
                'qr_length' => isset($response['qr_string']) ? strlen($response['qr_string']) : 0,
                'bank_reference' => $response['result']['referenceNo'] ?? null,
            ], '', 'uat_generate_qr');

            return ResponseHelper::successResponse($response);
        } catch (Throwable $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            LogHelper::sendErrorLog($ex, ['operation' => 'uat_generate_qr', 'repository_id' => $data['repositoryId'] ?? null, 'amount' => $data['amount'] ?? null], '', 'error_uat_generate_qr');

            return ResponseHelper::failedResponse($ex->getMessage(), 'UAT Generate QR Failed', 400);
        }
    }

    public function queryStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:255']]);

        try {
            $order = $this->uatOrder($data['reference']);
            $repository = $this->requireRepository((string) $order->getAttribute('payment_repository_id'));
            $order->setRelation('project', $this->uatProject($repository));

            LogHelper::sendLog('UAT Query Status - Request', [
                'reference' => $order->getReference(),
                'bill_number' => $order->getGatewayBillNumber(),
                'local_status' => $order->getStatus()?->value,
                'repository_id' => $repository->getAttribute('id'),
            ], '', 'uat_query_status');

            $bankStatus = $this->bankAgiService->checkStatus($order);

            LogHelper::sendLog('UAT Query Status - Bank Response', $bankStatus, '', 'uat_query_status');

            return ResponseHelper::successResponse([
                'reference' => $order->getReference(),
                'bill_number' => $order->getGatewayBillNumber(),
                'amount' => $order->getAmount(),
                'local_status' => $order->getStatus()?->value,
                'expired_at' => (string) $order->getAttribute('expired_at'),
                'bank_status' => $bankStatus,
            ]);
        } catch (Throwable $ex) {
            LogHelper::sendErrorLog($ex, ['operation' => 'uat_query_status', 'reference' => $data['reference'] ?? null], '', 'error_uat_query_status');

            return ResponseHelper::failedResponse($ex->getMessage(), 'UAT Query Status Failed', 400);
        }
    }

    public function notifyQris(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'latestTransactionStatus' => ['required', 'string', 'in:00,01,02,03,04,05,06,07'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
        ]);

        try {
            DB::beginTransaction();
            $order = $this->uatOrder($data['reference']);
            $repository = $this->requireRepository((string) $order->getAttribute('payment_repository_id'));
            $config = $repository->getValue();

            $amount = isset($data['amount']) ? $this->formatAmount($data['amount']) : $this->formatAmount($order->getAmount());
            $payload = [
                'originalReferenceNo' => $order->getGatewayReference() ?? $order->getReference(),
                'latestTransactionStatus' => $data['latestTransactionStatus'],
                'transactionStatusDesc' => $data['latestTransactionStatus'] === '00' ? 'SUCCEED' : 'FAILED',
                'destinationAccountName' => 'UAT TESTER',
                'issuerName' => 'Bank Artha Graha',
                'amount' => ['value' => $amount, 'currency' => 'IDR'],
                'additionalInfo' => [
                    'merchantId' => (string) ($config['merchant_id'] ?? ''),
                    'merchantUser' => (string) ($config['merchant_user'] ?? ''),
                    'billNumber' => (string) $order->getGatewayBillNumber(),
                ],
            ];

            $notifyRequest = Request::create('/api/bank/agi/notify-qris', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($payload, JSON_THROW_ON_ERROR));
            $notifyRequest->headers->set('X-PARTNER-ID', (string) ($config['client_key'] ?? 'UAT'));
            $notifyRequest->headers->set('X-TIMESTAMP', now()->toISOString());
            $notifyRequest->headers->set('X-EXTERNAL-ID', Str::random(32));
            $notifyRequest->headers->set('CHANNEL-ID', 'BAG-API');

            $result = $this->bankAgiService->callback($notifyRequest);
            DB::commit();

            $order->refresh();

            LogHelper::sendLog('UAT Notify - Success', [
                'reference' => $order->getReference(),
                'bill_number' => $order->getGatewayBillNumber(),
                'notify_status' => $data['latestTransactionStatus'],
                'notify_amount' => $amount,
                'notify_response' => $result,
                'local_status_after' => $order->getStatus()?->value,
                'status_changed' => $order->getStatus()?->value,
            ], '', 'uat_notify');

            return ResponseHelper::successResponse([
                'notify_response' => $result,
                'local_status' => $order->getStatus()?->value,
            ]);
        } catch (Throwable $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            LogHelper::sendErrorLog($ex, ['operation' => 'uat_notify', 'reference' => $data['reference'] ?? null, 'notify_status' => $data['latestTransactionStatus'] ?? null], '', 'error_uat_notify');

            return ResponseHelper::failedResponse($ex->getMessage(), 'UAT Notify Failed', 400);
        }
    }

    public function listOrders(Request $request): JsonResponse
    {
        $rows = Order::withoutEagerLoads()
            ->where('type', 'TEST')
            ->orderByDesc('id')
            ->limit((int) $request->query('per_page', 20))
            ->get()
            ->map(fn (Order $o) => [
                'reference' => $o->getReference(),
                'bill_number' => $o->getGatewayBillNumber(),
                'amount' => $o->getAmount(),
                'status' => $o->getStatus()?->value,
                'mode' => $o->getMode()?->value,
                'created_at' => (string) $o->getAttribute('created_at'),
            ]);

        return ResponseHelper::successResponse($rows);
    }

    public function negativeTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'case' => ['required', 'string', 'in:invalid_token,missing_token,invalid_signature,missing_mandatory,invalid_format,reused_external_id,invalid_bill_number,expired_timestamp'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $repository = PaymentRepository::withoutEagerLoads()
                ->whereHas('payment_gateway', fn ($q) => $q->where('key', ProjectSlug::BANK_AGI->value))
                ->orderByDesc('id')
                ->first();
            if (! $repository) {
                return ResponseHelper::failedResponse('No Bank AGI repository configured', 'Not Found', 404);
            }
            $config = $repository->getValue();
            $reference = $data['reference'] ?? null;

            $order = $reference ? $this->orders->latestByReference($reference, 'TEST') : null;
            $billNumber = $order?->getGatewayBillNumber() ?? (string) ($config['merchant_id'] ?? '') . '999999';
            $originalRef = $order?->getGatewayReference() ?? $order?->getReference() ?? 'UAT-NOT-EXISTS-999';
            $amountValue = $this->formatAmount($order?->getAmount() ?? 10000);

            $payload = [
                'originalReferenceNo' => $originalRef,
                'latestTransactionStatus' => '00',
                'transactionStatusDesc' => 'SUCCEED',
                'destinationAccountName' => 'UAT TESTER',
                'issuerName' => 'Bank Artha Graha',
                'amount' => ['value' => $amountValue, 'currency' => 'IDR'],
                'additionalInfo' => [
                    'merchantId' => (string) ($config['merchant_id'] ?? ''),
                    'merchantUser' => (string) ($config['merchant_user'] ?? ''),
                    'billNumber' => $billNumber,
                ],
            ];

            if ($data['case'] === 'missing_mandatory') {
                unset($payload['transactionStatusDesc'], $payload['additionalInfo']['merchantUser']);
            }
            if ($data['case'] === 'invalid_format') {
                $payload['latestTransactionStatus'] = '99';
            }
            if ($data['case'] === 'invalid_bill_number') {
                $payload['additionalInfo']['billNumber'] = '7000000000000000009999999';
            }

            $token = $this->issueUatAccessToken($repository);
            $headers = [
                'X-PARTNER-ID' => (string) ($config['client_key'] ?? 'UAT'),
                'X-TIMESTAMP' => $data['case'] === 'expired_timestamp' ? '2020-01-01T00:00:00+07:00' : now()->toISOString(),
                'X-EXTERNAL-ID' => Str::random(32),
                'CHANNEL-ID' => 'BAG-API',
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            if ($data['case'] === 'invalid_token') {
                $headers['Authorization'] = 'Bearer UAT-INVALID-TOKEN-000';
            }
            if ($data['case'] === 'missing_token') {
                unset($headers['Authorization']);
            }
            if ($data['case'] === 'invalid_signature' || $data['case'] === 'expired_timestamp') {
                $headers['X-SIGNATURE'] = 'deadbeef' . Str::random(56);
            }

            $notifyRequest = Request::create('/api/bank/agi/notify-qris', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], json_encode($payload, JSON_THROW_ON_ERROR));
            foreach ($headers as $key => $value) {
                $notifyRequest->headers->set($key, $value);
            }

            $controller = new BankAgiController;
            $response = $controller->callback($notifyRequest);

            LogHelper::sendLog('UAT Negative Test - Executed', [
                'case' => $data['case'],
                'http_status' => $response->getStatusCode(),
                'response' => $response->getData(true),
            ], '', 'uat_negative_test');

            return ResponseHelper::successResponse([
                'case' => $data['case'],
                'http_status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ]);
        } catch (Throwable $ex) {
            LogHelper::sendErrorLog($ex, ['operation' => 'uat_negative_test', 'case' => $data['case'] ?? null], '', 'error_uat_negative_test');

            return ResponseHelper::failedResponse($ex->getMessage(), 'UAT Negative Test Failed', 400);
        }
    }

    private function issueUatAccessToken(PaymentRepository $repository): string
    {
        $config = $repository->getValue();
        $timestamp = now()->toISOString();
        $clientKey = (string) ($config['client_key'] ?? '');
        $key = openssl_pkey_get_private(str_replace('\\n', "\n", (string) ($config['private_key'] ?? '')));
        if ($key === false || ! openssl_sign($clientKey . '|' . $timestamp, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new Exception('Unable to sign UAT access token request (check repository private_key)');
        }

        $tokenPayload = json_encode(['grantType' => 'client_credentials', 'additionalInfo' => []], JSON_THROW_ON_ERROR);
        $tokenRequest = Request::create('/api/bank/agi/access-token', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $tokenPayload);
        $tokenRequest->headers->set('X-CLIENT-KEY', $clientKey);
        $tokenRequest->headers->set('X-TIMESTAMP', $timestamp);
        $tokenRequest->headers->set('X-SIGNATURE', base64_encode($signature));

        $response = (new BankAgiController)->snapAccessTokenB2B($tokenRequest);
        $body = $response->getData(true);
        if (($body['responseCode'] ?? null) !== '2007300' || empty($body['accessToken'])) {
            LogHelper::sendErrorLog(new Exception('UAT access token failed'), [
                'operation' => 'uat_access_token',
                'response_code' => $body['responseCode'] ?? null,
                'response_message' => $body['responseMessage'] ?? null,
            ], '', 'error_uat_access_token');
            throw new Exception('UAT access token failed: ' . ($body['responseMessage'] ?? 'unknown'));
        }

        LogHelper::sendLog('UAT Access Token - Issued', [
            'response_code' => $body['responseCode'],
            'token_expires_in' => $body['expiresIn'] ?? null,
        ], '', 'uat_access_token');

        return (string) $body['accessToken'];
    }

    private function uatOrder(string $reference): Order
    {
        $order = $this->orders->latestByReference($reference, 'TEST');
        if (! $order) {
            throw new Exception('UAT order not found');
        }

        return $order;
    }

    private function requireRepository(string $id): PaymentRepository
    {
        $repository = $this->repositoryService->getById($id);
        if ($repository->payment_gateway?->key !== ProjectSlug::BANK_AGI->value) {
            throw new Exception('Bank AGI repository not found');
        }

        return $repository;
    }

    private function uatProject(PaymentRepository $repository): Project
    {
        $project = $this->projectService->firstOrCreate(
            ['type' => 'TEST'],
            [
                'name' => 'System Test Project',
                'slug' => ProjectSlug::BANK_AGI->value,
                'key' => 'test_key',
                'secure' => 'test_secure',
                'callback' => env('APP_URL', 'http://localhost:8000') . '/api/callback/' . ProjectSlug::BANK_AGI->value,
                'value' => 'test_project_token_system',
            ]
        );
        $project->setSlug(ProjectSlug::BANK_AGI);

        return $project;
    }

    private function uatOrderRequest(PaymentRepository $repository, array $data): Request
    {
        return new Request([
            'paymentRepositoryId' => $repository->getAttribute('id'),
            'merchantOrderId' => 'UAT-' . strtoupper(Str::random(12)),
            'paymentAmount' => $data['amount'],
            'paymentMethod' => 'qris',
            'mode' => $repository->getMode()?->value,
            'expiryPeriod' => $data['expiryPeriod'] ?? 60,
            'firstName' => 'UAT',
            'lastName' => 'Tester',
            'email' => 'uat-tester@example.com',
            'version' => '1',
        ]);
    }

    private function formatAmount(mixed $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim((string) $amount, '+'), 2), 2, '');

        return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 2, '0');
    }
}
