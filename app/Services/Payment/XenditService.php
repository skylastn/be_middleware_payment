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
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Services\Network\NetworkService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;

class XenditService
{
    private InvoiceApi $apiInstance;

    private PaymentRepositoryService $paymentRepositoryService;

    private OrderHistoryService $orderHistoryService;

    public function __construct()
    {
        $this->apiInstance = new InvoiceApi;
        $this->paymentRepositoryService = new PaymentRepositoryService;
        $this->orderHistoryService = new OrderHistoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? (env('IS_DEFAULT_SANDBOX', false) ? PaymentModeType::prod->value : PaymentModeType::sandbox->value);
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }

        return $this->paymentRepositoryService->getByPaymentGatewayKey('xendit', $modeValue);
    }

    public function order(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        if (! FormatHelper::isNotEmpty($paymentRepo)) {
            throw new Exception('Payment Repository Not Found');
        }
        $secretKey = $paymentRepo->getValue()['xendit_secretkey'] ?? '';
        $urlSuccess = $request->returnUrl ?? $request->return_url ?? '';
        // Xendit::setApiKey($secretKey);
        Configuration::setXenditKey($secretKey);

        $merchantOrderId = OrderIdGenerator::generate();
        $paymentAmount = $request->paymentAmount;
        // return $request->req;
        $req['id'] = $merchantOrderId;
        $req['reference'] = $project->type.'-'.$request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = '';
        $req['amount'] = (float) $paymentAmount;
        $customerName = trim(($request->firstName ?? '') . ' ' . ($request->lastName ?? ''));
        $req['name'] = $customerName ?: ($request->customerVaName ?? $request->name ?? null);
        $req['status'] = OrderStatus::PENDING->value;
        $req['return_url'] = $urlSuccess ?: $project->callback;

        $expired = ($request->expiryPeriod ?? 0) * 60;

        $params = [
            'external_id' => $req['reference'] ?? $project->type.'-'.$req['id'],
            'amount' => $paymentAmount ?? 0,
            'description' => $request->productDetails ?? 'Payment',
            'invoice_duration' => $expired,
            // 'payer_email' => $request->firstName,
            // 'customer' => [
            //     'given_names' => $request->firstName ?? "Artho",
            //     'surname' => $request->lastName ?? '',
            //     'email' => $request->email ??'artho@gmail.com',
            //     'mobile_number' => '+6287774441111',
            //     'addresses' => [
            //         [
            //             'city' => '',
            //             'country' => 'Indonesia',
            //             'postal_code' => '',
            //             'state' => '',
            //             'street_line1' => '',
            //             'street_line2' => ''
            //         ]
            //     ]
            // ],
            // 'customer_notification_preference' => [
            //     'invoice_created' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ],
            //     'invoice_reminder' => [
            //         'whatsapp',
            //         'sms',
            //         'email',
            //         'viber'
            //     ],
            //     'invoice_paid' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ],
            //     'invoice_expired' => [
            //         'whatsapp',
            //         // 'sms',
            //         'email',
            //         // 'viber'
            //     ]
            // ],
            'success_redirect_url' => $urlSuccess,
            'failure_redirect_url' => $project->callback,
            'currency' => 'IDR',
            // 'items' => [
            //     [
            //         'name' => 'Payment',
            //         'quantity' => 1,
            //         'price' => $request->paymentAmount ?? 0,
            //         'category' => 'Payment',
            //         'url' => $check['data']->callback
            //     ]
            // ],
            // 'fees' => [
            //     [
            //         'type' => 'ADMIN',
            //         'value' => 5000
            //     ]
            // ],
            'reminder_time' => 1,
        ];

        $create_invoice_request = new CreateInvoiceRequest($params);
        $req['request'] = json_encode($params);
        $order = Order::create($req);
        $order = Order::findOrFailCustom($order->id);
        LogHelper::sendLog(
            'Request Order Xendit',
            json_encode($order),
            $project->id,
            'request_order_xendit'
        );

        $createInvoice = $this->apiInstance->createInvoice($create_invoice_request);
        $result = json_encode($createInvoice);
        LogHelper::sendLog(
            'Response Order Xendit',
            json_encode($createInvoice),
            $project->id,
            'response_order_xendit'
        );
        $globalValue = $createInvoice['account_number'] ?? $createInvoice['qr_string'] ?? null;
        $order->setResponse($result);
        $order->setUrl($createInvoice['invoice_url'] ?? null);
        $order->setValue($globalValue);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::PENDING,
            'ORDER_CREATE_XENDIT',
            'Order created with status PENDING',
            $params,
            null
        );

        $response['message'] = 'Success Create Order';
        $response['link'] = $createInvoice['invoice_url'];
        $response['data'] = $createInvoice;

        return $response;
    }

    public function callback(Request $request): Order
    {
        $order = Order::where('reference', $request->external_id)->orderBy('id', 'DESC')->first();
        if (! $order) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);
        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());

        $xenditToken = (string) ($paymentRepo->getValue()['xendit_tokencallback'] ?? '');
        $incomingTokenXendit = (string) ($request->header('x-callback-token') ?? $request->header('X-Callback-Token') ?? '');

        if (empty($xenditToken) || empty($incomingTokenXendit) || ! hash_equals($xenditToken, $incomingTokenXendit)) {
            throw new Exception('You are not permitted perform this action', 403);
        }

        $status = OrderStatus::fromName($request->status);
        if (! $status) {
            throw new Exception('Status Undefined', 403);
        }

        $previousStatus = $order->status;
        $order->setCallback(json_encode($request->all()));
        $order->setStatus($status);
        $paymentCode = (string) ($request->payment_channel ?? $order->payment_method ?? 'xendit');
        $order->setPaymentMethod($paymentCode);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            $status,
            'WEBHOOK_XENDIT',
            "Xendit invoice webhook callback received: {$request->status}",
            $request->all(),
            $previousStatus
        );

        $split = explode('-', $request->external_id);
        $project = Project::where('type', $split[0])->first();
        LogHelper::sendLog(
            'Callback Xendit',
            json_encode($request->all()),
            $project->id,
            'callback_order_xendit'
        );
        if ($status->isSuccess()) {
            $params['merchantOrderId'] = $order->getMerchantOrderId();
            $params['paymentCode'] = $order->payment_method ?: $paymentCode;
            $params['resultCode'] = '00';
            $callback = RequestHelper::sendCallback($project->value, $params, $project->callback);
        }

        SendNotificationJob::dispatch($order->getReference());

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
        $secretKey = $config['xendit_secretkey'] ?? null;
        if (! $secretKey) {
            throw new Exception('Missing xendit_secretkey in repository configuration');
        }

        $limit = (int) ($filters['limit'] ?? $filters['per_page'] ?? 10);
        $queryParams = [
            'limit' => min($limit, 50),
        ];
        if (! empty($filters['start_date'])) {
            $queryParams['created_after'] = Carbon::parse($filters['start_date'])->startOfDay()->toIso8601String();
        }
        if (! empty($filters['end_date'])) {
            $queryParams['created_before'] = Carbon::parse($filters['end_date'])->endOfDay()->toIso8601String();
        }

        $headers = [
            'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
            'Accept' => 'application/json',
        ];
        $qs = http_build_query($queryParams);
        $url = 'https://api.xendit.co/v2/invoices' . ($qs ? '?' . $qs : '');

        $network = new NetworkService($url, NetworkType::GET, $headers);
        $responseBody = $network->sendAsync();

        $data = json_decode((string) $responseBody, true) ?: [];
        $invoices = is_array($data) ? $data : ($data['data'] ?? []);

        $items = [];
        foreach ($invoices as $inv) {
            $items[] = [
                'id' => $inv['id'] ?? '-',
                'reference' => $inv['external_id'] ?? $inv['id'] ?? '-',
                'amount' => (float) ($inv['amount'] ?? 0),
                'currency' => strtoupper($inv['currency'] ?? 'IDR'),
                'status' => strtoupper($inv['status'] ?? 'PENDING'),
                'payment_method' => $inv['payment_method'] ?? $inv['payment_channel'] ?? 'INVOICE',
                'customer' => $inv['payer_email'] ?? $inv['customer']['email'] ?? '-',
                'created_at' => isset($inv['created']) ? Carbon::parse($inv['created'])->toDateTimeString() : null,
                'raw' => $inv,
            ];
        }

        return [
            'gateway' => 'Xendit',
            'has_more' => count($items) >= $limit,
            'total' => count($items),
            'items' => $items,
        ];
    }
}
