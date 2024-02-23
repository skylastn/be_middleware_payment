<?php

namespace App\Services;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Setting;
use App\Repository\SPNPayRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SPNPayService
{
    static function setEnv(string $mode): array
    {
        $result = array();
        if ($mode == 'prod') {
            $result['secretKey']    = Setting::where('key', 'spnpay_secretkey_sandbox')->first()->value ?? '';
            $result['token']        = Setting::where('key', 'spnpay_token_sandbox')->first()->value ?? '';
            $result['url']          = Setting::where('key', 'url_spnpay_prod')->first()->value ?? '';
        } else {
            $result['secretKey']    = Setting::where('key', 'spnpay_secretkey_prod')->first()->value ?? '';
            $result['token']        = Setting::where('key', 'spnpay_token_prod')->first()->value ?? '';
            $result['url']          = Setting::where('key', 'url_spnpay_sandbox')->first()->value ?? '';
        }
        // set sanitizer (default : true)
        // $duitkuConfig->setSanitizedMode(false);
        return $result;
    }
    static function createOrderSPNPay(Request $request, Project $project)
    {
        $dateNow = date("Y-m-d H:i:s");
        $date = date("Y-m-d");

        try {

            DB::beginTransaction();

            $invID = Order::whereDate('created_at', $date)->orderBy('created_at', 'desc')->first();
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
            if (empty($request->lastName)) {
                $userName = $userName . ' ' . $request->lastName;
            }
            $params['viewName']                 = $userName;
            $params['additionalInfo']           = array(
                'callback' => env('APP_URL') . '/api/payment/callbackSPNPay',
            );

            $req['request']                     = json_encode($params);
            $order                              = Order::create($req);

            // $order->response                    = json_encode(SPNPayRepository::responseOrderFilter($response));
            $order->url                         = $paymentUrl;
            $order->status                      = 'PENDING';
            $order->save();

            $msg                                = "Success Create Order SPNPay";
            $result['link']                     = $paymentUrl;
            $result['result']                   = $order;
            DB::commit();
            return ResponseHelper::successResponse($result, $msg);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getFile(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    static function createOrderPaymentSPNPay(Request $request, Project $project, Order $order)
    {
        $dateNow = date("Y-m-d H:i:s");
        $date = date("Y-m-d");
        try {
            DB::beginTransaction();
            $config = SPNPayService::setEnv($order->mode);
            $paymemtMethod = PaymentMethod::where('value', $request->paymentMethod)->where('from', 'spnpay')->first();
            if (empty($paymemtMethod)) {
                throw new Exception("Sorry Payment Method Unavailable");
            }

            $url = $config['url'] . '/' . $paymemtMethod->key;
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
            $order->request     = json_encode($params);
            $signature = hash_hmac('sha512',  $config['secretKey'] . json_encode($params), $config['token']);
            // $signature = hash_hmac('sha512',  $config['secretKey'] . $order->request, $config['token']);
            $header = array(
                'On-Key: ' . $config['secretKey'],
                'On-Token: ' . $config['token'],
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
            $order->response        = json_encode(SPNPayRepository::responseOrderFilter($response->responseData));
            $order->save();

            $result['result'] = SPNPayRepository::responseOrderFilter($response->responseData);
            DB::commit();
            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getFile(), $ex->getMessage(), 400, $ex->getLine());
        }
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
}
