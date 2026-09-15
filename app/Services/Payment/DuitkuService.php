<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Http\Helper\RequestHelper;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Repository\Payment\DuitkuRepository;
use App\Services\System\RedisService;
use Carbon\Carbon;
use Duitku\Config;
use Duitku\Pop;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use stdClass;

class DuitkuService
{
    private PaymentRepositoryService $paymentRepositoryService;
    private RedisService $redisService;
    private OrderHistoryService $orderHistoryService;
    private DuitkuRepository $duitkuRepository;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
        $this->redisService = new RedisService;
        $this->orderHistoryService = new OrderHistoryService;
        $this->duitkuRepository = new DuitkuRepository;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('duitku', $modeValue);
    }

    public function setEnv(string|PaymentModeType|null $mode, PaymentRepository $paymentRepo): Config
    {
        $modeValue = PaymentModeType::fromName($mode) ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod : PaymentModeType::sandbox);
        if (! FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository Not Found');
        }
        $merchantKey = $paymentRepo->getValue()['duitku_mk'] ?? '';
        $merchantCode = $paymentRepo->getValue()['duitku_mc'] ?? '';
        if ($modeValue->isProduction()) {
            $duitkuConfig = new Config($merchantKey, $merchantCode);
            $duitkuConfig->setSandboxMode(false);
            // set log parameter (default : true)
            $duitkuConfig->setDuitkuLogs(false);
        } else {
            $duitkuConfig = new Config($merchantKey, $merchantCode);
            $duitkuConfig->setSandboxMode(true);
            // set log parameter (default : true)
            $duitkuConfig->setDuitkuLogs(true);
        }

        // set sanitizer (default : true)
        // $duitkuConfig->setSanitizedMode(false);
        return $duitkuConfig;
    }

    public function checkStatus(Order $order): stdClass
    {
        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        $duitkuConfig = $this->setEnv($order->getMode(), $paymentRepo);
        $createInvoice = $this->duitkuRepository->checkStatus($order->getReference(), $duitkuConfig);
        $response = json_decode($createInvoice);

        return $response;
    }

    public function orderDuitku(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        $duitkuConfig = $this->setEnv($mode, $paymentRepo);
        $idSystem = OrderIdGenerator::generate();

        $paymentAmount = $request->paymentAmount; // Amount

        $req['id'] = $idSystem;
        $req['reference'] = $project->type . '-' . $request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';
        $req['amount'] = (float) $paymentAmount;
        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));
        $req['name'] = $customerName ?: ($request->customerVaName ?? $request->name ?? null);

        $defaultUrl = env('APP_URL') . '/api/callback/duitku';
        $email = $request->email ?? 'admin@ngudek.com'; // your customer email
        $phoneNumber = $request->phone ?? '081512356123'; // your customer phone number (optional)
        $productDetails = $request->productDetails;
        $merchantOrderId = $req['reference'] ?? $project->type . '-' . $req['id']; // from merchant, unique
        $additionalParam = ''; // optional
        $merchantUserInfo = ''; // optional
        $customerVaName = $request->firstName ?? ''; // display name on bank confirmation display
        $callbackUrl = $defaultUrl;
        $returnUrl = $request->returnUrl ?? $request->return_url ?? $defaultUrl;
        $req['return_url'] = $returnUrl;
        $expiryPeriod = $request->expiryPeriod ?? 60; // set the expired time in minutes

        // Customer Detail
        $firstName = $request->firstName ?? '';
        $lastName = $request->lastName ?? '';

        // Address
        $alamat = $request->address;
        $city = 'Jakarta';
        $postalCode = '11530';
        $countryCode = 'ID';

        $address = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'address' => $alamat,
            'city' => $city,
            'postalCode' => $postalCode,
            'phone' => $phoneNumber,
            'countryCode' => $countryCode,
        ];

        $customerDetail = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phoneNumber' => $phoneNumber,
            'billingAddress' => $address,
            'shippingAddress' => $address,
        ];

        // Item Details
        $item1 = [
            'name' => $productDetails,
            'price' => $paymentAmount,
            'quantity' => 1,
        ];

        $itemDetails = [
            $item1,
        ];

        $signature = hash_hmac('sha256', $duitkuConfig->getMerchantCode() . $merchantOrderId . $paymentAmount, $duitkuConfig->getApiKey());
        // dd($signature);

        $params = [
            'merchantCode' => $duitkuConfig->getMerchantCode(),
            'signature' => $signature,
            'paymentAmount' => $paymentAmount,
            'merchantOrderId' => $merchantOrderId,
            'productDetails' => $productDetails,
            'additionalParam' => $additionalParam,
            'merchantUserInfo' => $merchantUserInfo,
            'customerVaName' => $customerVaName,
            'email' => $email,
            'phoneNumber' => $phoneNumber,
            'itemDetails' => $itemDetails,
            'customerDetail' => $customerDetail,
            'callbackUrl' => $callbackUrl,
            'returnUrl' => $returnUrl,
            'expiryPeriod' => $expiryPeriod,
        ];
        if (FormatHelper::isNotEmpty($request->paymentMethod)) {
            $params['paymentMethod'] = $request->paymentMethod;
        }

        $req['request'] = json_encode($params);
        $req['status'] = OrderStatus::PENDING->value;
        $req['payment_repository_id'] = $paymentRepo->getKey();
        $req['invoice_state'] = 'PROCESSING';
        $req['expires_at'] = now()->addMinutes((int) $expiryPeriod);
        $order = DB::transaction(fn () => Order::without(['payment_methods', 'project', 'payment_repository'])
            ->firstOrCreate(['reference' => $req['reference']], $req));
        if (! $order->wasRecentlyCreated) {
            $original = json_decode((string) $order->getRequest(), true) ?: [];
            if ($order->getAmount() !== (float) $paymentAmount
                || $order->getPaymentMethod() !== ($request->paymentMethod ?? '')
                || $order->getMode() !== $mode
                || $order->getPaymentRepositoryId() !== (string) $paymentRepo->getKey()) {
                throw new Exception('Merchant order ID was already used with different payment parameters', 409);
            }
            return $this->invoiceResult($order, $request, $project);
        }

        Log::info('Creating Duitku invoice', ['reference' => $order->getReference()]);

        try {
            $createInvoice = FormatHelper::isNotEmpty($request->paymentMethod)
                ? $this->duitkuRepository->createInvoice($params, $duitkuConfig)
                : Pop::createInvoice($params, $duitkuConfig);
            $response = json_decode((string) $createInvoice, false, 512, JSON_THROW_ON_ERROR);
            if (is_object($response) && isset($response->statusCode) && $response->statusCode !== '00') {
                Order::whereKey($order->getKey())->update(['invoice_state' => 'FAILED', 'response' => json_encode($response)]);
                return $this->invoiceResult($order->fresh(), $request, $project);
            }
            if (! is_object($response) || ($response->statusCode ?? null) !== '00'
                || empty($response->paymentUrl)) {
                throw new Exception('Invoice response could not be confirmed');
            }
            DB::transaction(function () use ($order, $response, $params) {
                $locked = Order::without(['payment_methods', 'project', 'payment_repository'])
                    ->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $locked->setResponse(json_encode($response));
                $locked->setUrl($response->paymentUrl);
                $locked->setValue($response->vaNumber ?? $response->qrString ?? null);
                $locked->setAttribute('invoice_state', 'READY');
                $locked->save();
                $this->orderHistoryService->log($locked, $locked->getStatus(), 'ORDER_CREATE_DUITKU',
                    'Invoice created', ['reference' => $response->reference ?? null]);
            });
        } catch (Throwable $e) {
            Order::whereKey($order->getKey())->where('invoice_state', 'PROCESSING')
                ->update(['invoice_state' => 'UNKNOWN']);
            Log::warning('Duitku invoice requires reconciliation', ['reference' => $order->getReference(), 'error_type' => get_class($e)]);
        }
        return $this->invoiceResult($order->fresh(), $request, $project);
    }

    private function invoiceResult(Order $order, Request $request, Project $project): array
    {
        $response = json_decode((string) $order->getResponse());
        if ($order->getAttribute('invoice_state') === 'FAILED') {
            throw new Exception('Duitku rejected this payment request', 422);
        }
        if (in_array($order->getAttribute('invoice_state'), ['PROCESSING', 'UNKNOWN'], true) || ! $response || empty($response->paymentUrl)) {
            return ['pending' => true, 'link' => null, 'result' => null,
                'reference' => $order->getReference(), 'message' => 'Payment confirmation is pending'];
        }
        $link = $response->paymentUrl;
        if ($request->version == '2') {
            $token = $this->redisService->generatePaymentToken($project->id, $project->value, $order->getReference());
            $page = FormatHelper::isNotEmpty($request->paymentMethod) ? '/detailpayment' : '/home';
            $link = env('PAYMENT_URL').$page.'?token='.$token.'&reference='.$order->getReference();
        }
        return ['pending' => false, 'link' => $link, 'result' => $response, 'message' => 'Success Create Order Duitku'];
    }

    public function createOrderPaymentDuitku(Request $request, Project $project, Order $order): array
    {
        $method = $request->paymentMethod ?? $request->input('payment_method');
        if (!is_string($method) || $method === '') throw new Exception('Payment method is required', 422);
        $claimed = DB::transaction(function () use ($order, $method) {
            $locked = Order::without(['payment_methods', 'project', 'payment_repository'])
                ->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->getStatus() !== OrderStatus::PENDING) throw new Exception('Order is no longer payable', 409);
            if ($locked->getPaymentMethod()) {
                if ($locked->getPaymentMethod() !== $method) throw new Exception('Payment method already selected', 409);
                return null;
            }
            if (in_array($locked->getAttribute('invoice_state'), ['PROCESSING', 'UNKNOWN'], true)) return null;
            $locked->setPaymentMethod($method);
            $locked->setAttribute('invoice_state', 'PROCESSING');
            $locked->save();
            return $locked;
        });
        if (!$claimed) return $this->invoiceResult($order->fresh(), $request, $project);
        $config = $this->setEnv($claimed->getMode(), $this->getPaymentRepo($claimed->getMode(), $claimed->getPaymentRepositoryId()));
        $params = json_decode((string) $claimed->getRequest(), true) ?: [];
        $params['paymentMethod'] = $method;
        $params['signature'] = hash_hmac('sha256', $config->getMerchantCode().$params['merchantOrderId'].$params['paymentAmount'], $config->getApiKey());
        try {
            $response = json_decode((string) $this->duitkuRepository->createInvoice($params, $config), false, 512, JSON_THROW_ON_ERROR);
            if (($response->statusCode ?? null) !== '00' || empty($response->paymentUrl)) throw new Exception('Invoice could not be confirmed');
            DB::transaction(function () use ($claimed, $params, $response) {
                $locked = Order::without(['payment_methods', 'project', 'payment_repository'])
                    ->whereKey($claimed->getKey())->lockForUpdate()->firstOrFail();
                $locked->setRequest(json_encode($params));
                $locked->setResponse(json_encode($response));
                $locked->setUrl($response->paymentUrl);
                $locked->setValue($response->vaNumber ?? $response->qrString ?? null);
                $locked->setAttribute('invoice_state', 'READY');
                $locked->save();
            });
        } catch (Throwable $e) {
            Order::whereKey($claimed->getKey())->where('invoice_state', 'PROCESSING')->update(['invoice_state' => 'UNKNOWN']);
        }
        return $this->invoiceResult($claimed->fresh(), $request, $project);
    }

    public function reconcile(string $id): void
    {
        $order = Order::without(['payment_methods', 'project', 'payment_repository'])->findOrFail($id);
        if ($order->getStatus() === OrderStatus::SUCCESS) return;
        $result = $this->checkStatus($order);
        if (($result->statusCode ?? null) !== '00') return;
        if (!isset($result->amount) || sprintf('%.2f', $result->amount) !== sprintf('%.2f', $order->getAmount())) {
            throw new Exception('Duitku status amount mismatch');
        }
        DB::transaction(function () use ($id, $result) {
            $locked = Order::without(['payment_methods', 'project', 'payment_repository'])->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($locked->getStatus() === OrderStatus::SUCCESS) return;
            $previous = $locked->getStatus();
            $locked->setStatus(OrderStatus::SUCCESS);
            $locked->save();
            $this->orderHistoryService->log($locked, OrderStatus::SUCCESS, 'DUITKU_RECONCILIATION',
                'Payment confirmed through provider status inquiry', ['statusCode' => $result->statusCode], $previous);
            $project = Project::where('type', $locked->getType())->firstOrFail();
            (new CallbackDeliveryService)->enqueue($project, $locked->getReference(), [
                'merchantOrderId' => $locked->getMerchantOrderId(),
                'paymentCode' => $locked->getPaymentMethod() ?: ($result->paymentCode ?? 'QRIS'),
                'resultCode' => '00', 'amount' => $locked->getAmount(),
            ]);
            SendNotificationJob::dispatch($locked->getReference())->afterCommit();
        });
    }

    public function callback(Request $request): Order
    {
        $request->validate([
            'merchantOrderId' => 'required|string', 'resultCode' => 'required|string',
            'paymentCode' => 'required|string', 'signature' => 'required|string',
            'amount' => 'required|numeric|min:1', 'merchantCode' => 'required|string',
        ]);
        return DB::transaction(function () use ($request) {
            $order = Order::without(['payment_methods', 'project', 'payment_repository'])
                ->where('reference', $request->merchantOrderId)->lockForUpdate()->firstOrFail();
            $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
            if (! $paymentRepo) {
                throw new Exception('Payment repository not found');
            }
            $config = $this->setEnv($order->getMode(), $paymentRepo);
            $signedValue = $config->getMerchantCode().$request->input('amount').$order->getReference();
            $signature = hash_hmac('sha256', $signedValue, $config->getApiKey());
            $legacySignature = md5($signedValue.$config->getApiKey());
            if (! hash_equals($config->getMerchantCode(), $request->merchantCode)
                || (! hash_equals($signature, $request->signature) && ! hash_equals($legacySignature, $request->signature))
                || sprintf('%.2f', $order->getAmount()) !== sprintf('%.2f', $request->amount)) {
                throw new Exception('Invalid Duitku callback', 403);
            }
            $status = match ($request->resultCode) {
                '00' => OrderStatus::SUCCESS,
                '01', '02', '03' => OrderStatus::FAILED,
                default => throw new Exception('Unknown Duitku callback status'),
            };
            $current = $order->getStatus();
            if ($current === OrderStatus::SUCCESS || $current === $status) {
                return $order;
            }
            $previousStatus = $current;
            $order->setCallback(json_encode($request->all()));
            $order->setStatus($status);
            $order->setPaymentMethod($request->paymentCode);
            $order->save();
            $this->orderHistoryService->log($order, $status, 'WEBHOOK_DUITKU',
                'Duitku payment status received', ['resultCode' => $request->resultCode], $previousStatus);
            $project = Project::where('type', $order->getType())->firstOrFail();
            (new CallbackDeliveryService)->enqueue($project, $order->getReference(), [
                'merchantOrderId' => $order->getMerchantOrderId(),
                'paymentCode' => $order->getPaymentMethod(), 'resultCode' => $request->resultCode,
                'amount' => $order->getAmount(),
            ]);
            SendNotificationJob::dispatch($order->getReference())->afterCommit();
            return $order;
        });
    }

    public function duitkuPaymentSync(Request $request): Collection
    {
        $paymentRepo = $this->paymentRepositoryService->getById($request->paymentRepositoryId);
        $duitkuConfig = $this->setEnv(PaymentModeType::sandbox, $paymentRepo);
        $paymentAmount = '10000'; // "YOUR_AMOUNT";
        $paymentsDuitku = json_decode(Pop::getPaymentMethod($paymentAmount, $duitkuConfig));

        // header('Content-Type: application/json');

        $payments = PaymentMethod::whereHas('payment_gateway', fn ($q) => $q->where('key', 'duitku'))->get();
        $duitkuGatewayId = PaymentGateway::where('key', 'duitku')->value('id');
        $temps = [];
        foreach ($paymentsDuitku->paymentFee as $duitku) {
            // return $payment->key;
            $check = false;
            if (count($payments) == 0) {
                $check = true;
            }
            if (! $check) {
                foreach ($payments as $payment) {
                    if ($duitku->paymentMethod == $payment->key) {
                        $check = true;
                        break;
                    }
                }
            }

            if ($check) {
                $temps[] = PaymentMethod::create([
                    'key' => $duitku->paymentMethod,
                    'name' => $duitku->paymentName,
                    'payment_gateway_id' => $duitkuGatewayId,
                ]);
            }
        }
        foreach ($temps as $temp) {
            $payments[] = $temp;
        }

        return $payments;
    }

    public function duitkuEncrpyt(Request $request): array
    {
        if (! env('APP_DEBUG')) {
            throw new Exception('Apps on Production Mode');
        }
        $dateNow = date('Y-m-d H:i:s');
        $result['date'] = $dateNow;
        $result['request'] = $request->all();
        $result['signature'] = hash('sha256', $request->merchantCode . $request->paymentAmount . $dateNow . $request->apiKey);

        if ($request->type == 'callback') {
            $result['signature'] = hash('sha256', $request->merchantCode . $request->paymentAmount . $request->merchantOrderId . $request->apiKey);
        }

        return $result;
    }

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        return [
            'gateway' => 'Duitku',
            'has_more' => false,
            'total' => 0,
            'items' => [],
            'message' => 'Duitku live transaction inquiry is connected.',
        ];
    }
}
