<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use App\Repository\Payment\SPNPayRepository;
use Exception;
use Illuminate\Http\Request;

class SPNPayService
{
    private PaymentRepositoryService $paymentRepositoryService;
    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService();
    }

    public function getPaymentRepo(string $mode, $id): ?PaymentRepository
    {
        if (FormatHelper::isNotEmpty($id)) {
            return $this->paymentRepositoryService->getById($id);
        }
        return $this->paymentRepositoryService->getByPaymentGatewayKey('spnpay', $mode);
    }

    public function createOrderSPNPay(Request $request, Project $project): array
    {
        $date = date("Y-m-d");

        $invID                              = Order::whereDate('created_at', $date)->orderBy('created_at', 'desc')->first();
        $invIDCount                         = substr($invID->id ?? 00000, -5);
        $invID_num                          = (int)$invIDCount + 1;
        $idSystem                           = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);

        $req['id']                          = $idSystem;
        $req['reference']                   = $project->type . '-' . $request->merchantOrderId;
        $req['type']                        = $project->type;
        $req['mode']                        = $request->mode ?? "sandbox";
        $req['payment_method']              = $request->paymentMethod ?? '';
        $paymentUrl                         = env('PAYMENT_URL') . '/#/home' . '?reference=' . $req['reference'];
        $req['url']                         = $paymentUrl;
        $req['notes']                       = $request->productDetails;
        $req['address']                     = $request->address;
        $req['phone']                       = $request->phone;
        $req['email']                       = $request->email;

        $params['singleUse']                = true;
        $params['type']                     = 'ClosedAmount';
        $params['reference']                = $req['reference'];
        $params['amount']                   = $request->paymentAmount;
        $params['expiryMinutes']            = 60;
        $userName                           = $request->firstName ?? "AndalanSoftware";
        if (FormatHelper::isNotEmpty($request->lastName)) {
            $userName = $userName . ' ' . $request->lastName;
        }
        $params['viewName']                 = $userName;
        $params['additionalInfo']           = array(
            'callback' => env('APP_URL') . '/api/payment/callbackSPNPay',
        );

        $req['request']                     = json_encode($params);
        $order                              = Order::createAndFind($req);

        // $order->response                    = json_encode(SPNPayRepository::responseOrderFilter($response));
        $order->url                         = $paymentUrl;
        $order->status                      = 'PENDING';
        $order->save();

        $result['link']                     = $paymentUrl;
        $result['result']                   = $order;
        return $result;
    }

    public function createOrderPaymentSPNPay(Request $request, Project $project, Order $order)
    {
        $paymentRepo = $this->getPaymentRepo($order->mode, $request->paymentGatewayId);
        $paymemtMethod = PaymentMethod::where('value', $request->paymentMethod)->where('from', 'spnpay')->first();
        if (!FormatHelper::isNotEmpty($paymemtMethod)) {
            throw new Exception("Sorry Payment Method Unavailable");
        }

        $url = $paymentRepo['url_spnpay'] . '/' . $paymemtMethod->key;
        $requestOrder = json_decode($order->request);

        $params['bankCode']                 = $paymemtMethod->bankCode;
        $params['singleUse']                = $requestOrder->singleUse;
        $params['type']                     = $requestOrder->type;
        $params['reference']                = $requestOrder->reference;
        $params['amount']                   = $requestOrder->amount;
        $params['expiryMinutes']            = $requestOrder->expiryMinutes;
        $params['viewName']                 = $requestOrder->viewName;
        $params['additionalInfo']           = array(
            'callback' => env('APP_URL') . '/api/payment/callbackSPNPay',
        );
        $order->setRequest(json_encode($params));
        $order->setPaymentMethod($request->paymentMethod ?? '');
        $signature = hash_hmac('sha512',  $paymentRepo['spnpay_secretkey'] . json_encode($params), $paymentRepo['spnpay_token']);
        // $signature = hash_hmac('sha512',  $config['secretKey'] . $order->request, $config['token']);
        $header = array(
            'On-Key: ' . $paymentRepo['spnpay_secretkey'],
            'On-Token: ' . $paymentRepo['spnpay_token'],
            'On-Signature: ' . $signature,
            'Accept: application/json',
            'Content-Type: application/json'
        );
        $req['header']      = json_encode($header);
        $req['url']         = $url;
        $req['request']     = $params;

        LogHelper::sendLog(
            'Request Order SPNPay',
            $req,
            $project->id,
            'request_order_spnpay'
        );
        // return $req;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => $header,
        ));

        $createInvoice = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($createInvoice);

        LogHelper::sendLog(
            'Response Order SPNPay',
            $response,
            $project->id,
            'response_order_spnpay'
        );
        $order->setPaymentRepositoryId($paymentRepo->id);
        $order->setResponse(json_encode(SPNPayRepository::responseOrderFilter($response->responseData)));
        $order->save();

        $result['result'] = SPNPayRepository::responseOrderFilter($response->responseData);
        return $result;
    }

    // static function getUrlPathPayment(string $paymentType): string
    // {
    //     switch ($paymentType) {
    //         case 'virtual-account':
    //             return 'virtual-account';
    //             break;
    //         case 'qris':
    //             return 'qris';
    //             break;
    //         case 'e-wallet':
    //             return 'e-wallet';
    //             break;
    //         case 'retail':
    //             return 'retail';
    //             break;
    //         case 'credit-card':
    //             return 'credit-card';

    //         default:
    //             # code...
    //             break;
    //     }
    //     return '';
    // }

    public function callback(Request $request): Order
    {
        LogHelper::sendLog(
            'Callback SPNPay',
            json_encode($request->all()),
            '0',
            'callback_order_spnpay'
        );
        $order = Order::where("reference", $request->responseData['merchantRef'])->orderBy('id', 'DESC')->first();

        if (!FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order not found');
        }
        $order = Order::findOrFailCustom($order->id);

        $status = 'PENDING';
        $resultCode = '00';
        switch ($request->responseData['status']) {
            case 'success':
                $status = 'PAID';
                break;
            case 'failed':
                $status = 'FAILED';
                $resultCode = '01';
                break;
            case 'expired':
                $status = 'Expired';
                $resultCode = '02';
                break;
            default:
                break;
        }
        if (FormatHelper::isNotEmpty($status)) {
            throw new Exception('Status not found');
        }

        $order->setCallback(json_encode($request->all()));
        $order->setStatus($status);
        $order->save();

        $paymentMethod          = PaymentMethod::where("value", $order->payment_method)->first();

        if (!FormatHelper::isNotEmpty($paymentMethod)) {
            throw new Exception('Payment not found');
        }

        $split              = explode("-", $order->reference);
        $project            = Project::where("type", $split[0])->first();
        LogHelper::sendLog(
            'Callback SPNPay',
            json_encode($order->callback),
            $project->id,
            'callback_order_spnpay'
        );
        $params['merchantOrderId']  = $split[1] . "-" . $split[2];
        $params['paymentCode']      = $order->payment_method;
        $params['resultCode']       = $resultCode;
        $callback                   = RequestHelper::sendCallback($project->value, $params, $project->callback);
        $order->refresh();
        return $order;
    }
}
