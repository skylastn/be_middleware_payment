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
use App\Repository\Payment\DuitkuRepository;
use Carbon\Carbon;
use Duitku\Config;
use Duitku\Pop;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use stdClass;

class DuitkuService
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

        return $this->paymentRepositoryService->getByPaymentGatewayKey('duitku', $modeValue);
    }

    public function setEnv(string|PaymentModeType|null $mode, PaymentRepository $paymentRepo): Config
    {
        $modeValue = PaymentModeType::fromName($mode) ?? PaymentModeType::sandbox;
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
        $createInvoice = Pop::transactionStatus($order->reference, $duitkuConfig);
        $response = json_decode($createInvoice);

        return $response;
    }

    public function orderDuitku(Request $request, Project $project): array
    {
        $mode = PaymentModeType::fromName($request->mode) ?? PaymentModeType::sandbox;
        $paymentRepo = $this->getPaymentRepo($mode, $request->paymentRepositoryId);
        $duitkuConfig = $this->setEnv($mode, $paymentRepo);
        $idSystem = OrderIdGenerator::generate();

        $req['id'] = $idSystem;
        $req['reference'] = $project->type . '-' . $request->merchantOrderId;
        $req['type'] = $project->type;
        $req['mode'] = $mode->value;
        $req['payment_method'] = $request->paymentMethod ?? '';

        $defaultUrl = env('APP_URL') . '/api/callback/duitku';
        $paymentAmount = $request->paymentAmount; // Amount
        $email = $request->email ?? 'admin@ngudek.com'; // your customer email
        $phoneNumber = $request->phone ?? '081512356123'; // your customer phone number (optional)
        $productDetails = $request->productDetails;
        $merchantOrderId = $req['reference'] ?? $project->type . '-' . $req['id']; // from merchant, unique
        $additionalParam = ''; // optional
        $merchantUserInfo = ''; // optional
        $customerVaName = $request->firstName ?? ''; // display name on bank confirmation display
        $callbackUrl = $defaultUrl;
        $returnUrl = $request->returnUrl ?? $defaultUrl;
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

        $signature = md5($duitkuConfig->getMerchantCode() . $merchantOrderId . $paymentAmount . $duitkuConfig->getApiKey());
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
        $order = Order::createAndFind($req);

        LogHelper::sendLog(
            'Request Order Duitku',
            $req,
            $project->id,
            'request_order_duitku'
        );

        if (FormatHelper::isNotEmpty($request->paymentMethod)) {
            $createInvoice = (new DuitkuRepository($duitkuConfig))
                ->createInvoice($params, $duitkuConfig);
        } else {
            $createInvoice = Pop::createInvoice($params, $duitkuConfig);
        }

        $response = json_decode($createInvoice);

        LogHelper::sendLog(
            'Response Order Duitku',
            $response,
            $project->id,
            'response_order_duitku'
        );

        $order->setResponse(json_encode($response));
        $order->setUrl($response->paymentUrl);
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->save();

        $msg = 'Success Create Order Duitku';
        $result['link'] = $response->paymentUrl;
        if (FormatHelper::isNotEmpty($request->version) && $request->version == '2') {
            $result['link'] = env('PAYMENT_URL') . '/detailpayment?token=' . $project->value . '&reference=' . $order->reference;
        }
        $result['result'] = $response;
        $result['message'] = $msg;

        return $result;
    }

    public function callback(Request $request): Order
    {
        LogHelper::sendLog('callback', $request->all());
        $now = Carbon::now();
        $dateBefore = date('Y-m-d', strtotime('-1 week'));
        $order = Order::where('reference', $request->merchantOrderId)
            ->whereBetween('created_at', [$dateBefore, $now])
            ->orderBy('id', 'DESC')->first();
        if (! $order) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);
        $paymentRepo = $this->getPaymentRepo($order->getMode(), $order->getPaymentRepositoryId());
        $duitkuConfig = $this->setEnv($order->getMode(), $paymentRepo);
        $callback = Pop::callback($duitkuConfig);
        // header('Content-Type: application/json');
        $notif = json_decode((string) $callback);

        // var_dump($callback);
        $status = '';
        if ($notif->resultCode == '00') {
            $status = OrderStatus::SUCCESS;
        } elseif ($notif->resultCode == '01') {
            $status = OrderStatus::FAILED;
        }

        if (! FormatHelper::isNotEmpty($status)) {
            throw new Exception("Status Undefined : $status");
        }
        $reference = $request->merchantOrderId;
        $order->setCallback(json_encode($request->all()));
        $order->setStatus($status);
        $paymentMethod = PaymentMethod::where('key', $request->paymentCode)->where('from', 'duitku')->first();

        if (! FormatHelper::isNotEmpty($paymentMethod)) {
            throw new Exception('Payment not found : ' . $request->paymentCode);
        }

        $order->setPaymentMethod($paymentMethod->value);
        $order->save();

        $split = explode('-', $reference);
        $project = Project::where('type', $split[0])->first();
        if (! FormatHelper::isNotEmpty($project)) {
            throw new Exception('Project Not Found');
        }
        LogHelper::sendLog(
            'Callback Duitku',
            json_encode($order->getCallback()),
            $project->id,
            'callback_order_midtrans'
        );
        $idSend = '';
        for ($i = 0; $i <= count($split) - 1; $i++) {
            if ($i == 0) {
                continue;
            }
            if ($i == 1) {
                $idSend = $split[$i];

                continue;
            }
            $idSend = $idSend . '-' . $split[$i];
        }
        $params['merchantOrderId'] = $idSend;
        $params['paymentCode'] = $order->getPaymentMethod();
        $params['resultCode'] = $notif->resultCode;
        $callback = RequestHelper::sendCallback($project->value, $params, $project->callback);

        try {
            NotificationService::sendNotification($reference);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
        }
        $order->refresh();

        return $order;
    }

    public function duitkuPaymentSync(Request $request): Collection
    {
        $paymentRepo = $this->paymentRepositoryService->getById($request->paymentRepositoryId);
        $duitkuConfig = $this->setEnv(PaymentModeType::sandbox, $paymentRepo);
        $paymentAmount = '10000'; // "YOUR_AMOUNT";
        $paymentsDuitku = json_decode(Pop::getPaymentMethod($paymentAmount, $duitkuConfig));

        // header('Content-Type: application/json');

        $payments = PaymentMethod::where('from', 'duitku')->get();
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
                    'value' => $duitku->paymentMethod,
                    'name' => $duitku->paymentName,
                    'type' => '',
                    'from' => 'duitku',
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
}
