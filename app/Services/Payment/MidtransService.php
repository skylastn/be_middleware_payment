<?php

namespace App\Services\Payment;

use App\Enums\NetworkType;
use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Http\Helper\RequestHelper;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Network\NetworkService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransService
{
    private PaymentRepositoryService $paymentRepositoryService;
    private OrderHistoryService $orderHistoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
        $this->orderHistoryService = new OrderHistoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('midtrans', $modeValue);
    }

    public function orderMidtrans(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentGatewayId);
        $merchantOrderId = OrderIdGenerator::generate();

        $req['id'] = $merchantOrderId;
        $req['reference'] = $project->type.'-'.$request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = '';
        $req['status'] = OrderStatus::PENDING->value;

        $transactionDetails['order_id'] = $req['reference'] ?? $project->type.'-'.$req['id'];
        $transactionDetails['gross_amount'] = $request->paymentAmount ?? 0;
        $creditCard['secure'] = true;
        $customerDetails['first_name'] = $request->firstName ?? '';
        $customerDetails['last_name'] = $request->lastName ?? '';
        $customerDetails['email'] = $request->email ?? 'xfit.id@gmail.com';
        $customerDetails['phone'] = $request->phone ?? '081512356123';

        $params = [
            'transaction_details' => $transactionDetails,
            'credit_card' => $creditCard,
            'customer_details' => $customerDetails,
        ];

        $req['request'] = json_encode($params);
        $order = Order::createAndFind($req);
        LogHelper::sendLog(
            'Request Order Midtrans',
            json_encode($order),
            $project->id,
            'request_order_midtrans'
        );

        $createInvoice = $this->createTransactionMidtrans($params, $paymentRepo);
        $result = json_encode($createInvoice);
        LogHelper::sendLog(
            'Response Order Midtrans',
            json_encode($createInvoice),
            $project->id,
            'response_order_midtrans'
        );
        if ($createInvoice['statusCode'] != 201) {
            throw new Exception($createInvoice['response']->error_messages[0], $createInvoice['statusCode']);
        }
        $resData = is_array($createInvoice['response']) ? (object) $createInvoice['response'] : $createInvoice['response'];
        $globalValue = $resData->qr_string
            ?? (isset($resData->va_numbers[0]->va_number) ? $resData->va_numbers[0]->va_number : null)
            ?? (isset($resData->va_numbers[0]['va_number']) ? $resData->va_numbers[0]['va_number'] : null)
            ?? ($resData->permata_va_number ?? null)
            ?? ($resData->bca_va_number ?? null)
            ?? ($resData->bri_va_number ?? null)
            ?? ($resData->bni_va_number ?? null)
            ?? ($resData->bill_key ?? null)
            ?? null;

        $order->setResponse($result);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->setUrl($resData->redirect_url ?? null);
        $order->setValue($globalValue);
        $order->save();

        $response['link'] = $createInvoice['response']->redirect_url;
        $response['result'] = $createInvoice['response'];

        return $response;
    }

    public function createTransactionMidtrans(array $body, PaymentRepository $paymentRepo): array
    {
        $urlOrderMidtrans = $paymentRepo->getValue()['midtrans_url'];
        $serverKey = $paymentRepo->getValue()['midtrans_serverkey'];
        $encodedKey = base64_encode($serverKey.':');

        try {
            $response = Http::timeout(30)
                ->retry(2, 1000)
                ->withHeaders([
                    'Authorization' => "Basic {$encodedKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post($urlOrderMidtrans, $body);

            $result['response'] = $response->json();
            $result['statusCode'] = $response->status();
        } catch (Exception $e) {
            $result['response'] = (object) ['error_messages' => [$e->getMessage()]];
            $result['statusCode'] = 500;
        }

        return $result;
    }

    public function callback(Request $request): Order
    {
        $order = Order::where('reference', $request->order_id)->orderBy('id', 'DESC')->first();

        if (! $order) {
            throw new Exception('Order not found');
        }

        $order = Order::findOrFailCustom($order->id);
        $mode = $order->getMode() ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $order->getPaymentRepositoryId());
        Config::$serverKey = $paymentRepo->getValue()['midtrans_serverkey'];
        if ($mode === PaymentModeType::sandbox) {
            Config::$isProduction = false;
        }
        if ($mode->isProduction()) {
            Config::$isProduction = true;
        }

        $notifs = new Notification;
        $notif = (object) $notifs->getResponse();
        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $reference = $notif->order_id;
        $fraud = $notif->fraud_status;
        $status = match ($transaction) {
            'capture' => $type === 'credit_card' && $fraud === 'challenge'
                ? OrderStatus::FAILED
                : OrderStatus::SUCCESS,
            'settlement' => OrderStatus::SUCCESS,
            'pending' => OrderStatus::PENDING,
            'expire' => OrderStatus::EXPIRED,
            'deny', 'cancel' => OrderStatus::FAILED,
            default => null,
        };

        if (! FormatHelper::isNotEmpty($status)) {
            throw new Exception('Status Undefined', 403);
        }

        if (! $status->isSuccess()) {
            throw new Exception('Status '.$status->value, 403);
        }

        $previousStatus = $order->status;
        $order->setCallback(json_encode($request->all()));
        $order->setStatus($status);

        if ($type == 'bank_transfer') {
            if (empty($request->bank) || $request->bank == 'permata') {
                $paymentMethod = PaymentMethod::where('key', $type)->where('type', 'permata')->first();
            } else {
                $paymentMethod = PaymentMethod::where('key', $type)->where('type', $request->bank)->first();
            }
        } else {
            $paymentMethod = PaymentMethod::where('key', $type)->first();
        }

        if (! FormatHelper::isNotEmpty($paymentMethod)) {
            throw new Exception('Payment Method not found');
        }

        $order->setPaymentMethod($paymentMethod->value);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            $status,
            'WEBHOOK_MIDTRANS',
            "Midtrans transaction status received: {$transaction}",
            $request->all(),
            $previousStatus
        );

        $split = explode('-', $reference);
        $project = Project::where('type', $split[0])->first();
        LogHelper::sendLog(
            'Callback Midtrans',
            json_encode($order->callback),
            $project->id,
            'callback_order_midtrans'
        );
        $params['merchantOrderId'] = $order->getMerchantOrderId();
        $params['paymentCode'] = $order->getPaymentMethod();
        $params['resultCode'] = '00';
        $callback = RequestHelper::sendCallback($project->value, $params, $project->callback);

        SendNotificationJob::dispatch($reference);

        $order->refresh();

        return $order;
    }

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        $config = is_array($repository->value) ? $repository->value : (json_decode((string) $repository->value, true) ?: []);
        $serverKey = $config['midtrans_serverkey'] ?? null;
        if (! $serverKey) {
            throw new Exception('Missing midtrans_serverkey in repository configuration');
        }

        $isSandbox = $repository->mode === PaymentModeType::sandbox || $repository->mode === 'sandbox' || str_starts_with($serverKey, 'SB-');
        $baseUrl = $isSandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
        $limit = (int) ($filters['limit'] ?? $filters['per_page'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
            'Accept' => 'application/json',
        ];
        $qs = http_build_query([
            'page' => $page,
            'per_page' => min($limit, 50),
        ]);
        $url = "{$baseUrl}/v2/history?{$qs}";

        $items = [];
        try {
            $network = new NetworkService($url, NetworkType::GET, $headers);
            $responseBody = $network->sendAsync();
            $data = json_decode((string) $responseBody, true) ?: [];
            $records = $data['records'] ?? $data['data'] ?? [];
            foreach ($records as $row) {
                $items[] = [
                    'id' => $row['transaction_id'] ?? $row['order_id'] ?? '-',
                    'reference' => $row['order_id'] ?? '-',
                    'amount' => (float) ($row['gross_amount'] ?? 0),
                    'currency' => strtoupper($row['currency'] ?? 'IDR'),
                    'status' => strtoupper($row['transaction_status'] ?? 'PENDING'),
                    'payment_method' => $row['payment_type'] ?? '-',
                    'customer' => $row['customer_details']['email'] ?? '-',
                    'created_at' => $row['transaction_time'] ?? null,
                    'raw' => $row,
                ];
            }
        } catch (\Throwable $e) {
            // Log and return items
        }

        return [
            'gateway' => 'Midtrans',
            'has_more' => count($items) >= $limit,
            'total' => count($items),
            'items' => $items,
        ];
    }
}
