<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\OrderIdGenerator;
use App\Http\Helper\RequestHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransService
{
    private PaymentRepositoryService $paymentRepositoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
    }

    public function getPaymentRepo(string|PaymentModeType|null $mode, int|string|null $id): ?PaymentRepository
    {
        $modeValue = PaymentModeType::fromName($mode)?->value ?? PaymentModeType::sandbox->value;
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
        $order->setResponse($result);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->setUrl($createInvoice['response']->redirect_url);
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

        $split = explode('-', $reference);
        $project = Project::where('type', $split[0])->first();
        LogHelper::sendLog(
            'Callback Midtrans',
            json_encode($order->callback),
            $project->id,
            'callback_order_midtrans'
        );
        $params['merchantOrderId'] = $split[1].'-'.$split[2];
        $params['paymentCode'] = $order->getPaymentMethod();
        $params['resultCode'] = '00';
        $callback = RequestHelper::sendCallback($project->value, $params, $project->callback);

        $order->refresh();

        return $order;
    }
}
