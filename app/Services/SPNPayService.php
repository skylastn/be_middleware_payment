<?php

namespace App\Services;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\Project;
use App\Models\Setting;
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
            $result['url']          = Setting::where('key', 'url_spnpay_sandbox')->first()->value ?? '';
        } else {
            $result['secretKey']    = Setting::where('key', 'spnpay_secretkey_prod')->first()->value ?? '';
            $result['token']        = Setting::where('key', 'spnpay_token_prod')->first()->value ?? '';
            $result['url']          = Setting::where('key', 'url_spnpay_prod')->first()->value ?? '';
        }
        // set sanitizer (default : true)
        // $duitkuConfig->setSanitizedMode(false);
        return $result;
    }
    static function orderSPNPay(Request $request, Project $project)
    {
        $dateNow = date("Y-m-d H:i:s");
        $date = date("Y-m-d");

        try {

            DB::beginTransaction();

            $invID = DB::table('orders')->whereDate('created_at', $date)->orderBy('created_at', 'desc')->first();
            $invIDCount                         = substr($invID->id ?? 00000, -5);
            $invID_num                          = (int)$invIDCount + 1;
            $idSystem                           = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);

            $req['id']                          = $idSystem;
            $req['reference']                   = $project->type . '-' . $request->merchantOrderId;
            $req['type']                        = $project->type;
            $req['mode']                        = $request->mode ?? "sandbox";
            $req['payment_method']              = $request->paymentMethod ?? '';

            $params['bankCode']                 = '014';
            $params['singleUse']                = true;
            $params['type']                     = 'ClosedAmount';
            $params['reference']                = $idSystem;
            $params['amount']                   = $request->paymentAmount;
            $params['expiryMinutes']            = 60;
            $params['viewName']                 = $request->firstName ?? "AndalanSoftware";
            $params['additionalInfo']           = array(
                'callback' => env('APP_URL') . '/api/callbackSPNPay',
            );


            $req['request']         = json_encode($params);
            $order                  = Order::create($req);
            $params = '{
    "paymentAmount": 15000,
    "paymentMethod": "",
    "merchantOrderId": "20220912-00044",
    "productDetails": "Pembayaran SPNPay",
    "expiryPeriod": 10,
    "mode" : "sandbox",
    "firstName" : "Sahid",
    "lastName" : "R",
    "email" : "sahidrahutomo@gmail.com",
    "address" : "klaten",
    "phone" : "08815123766"
}';
            $config = SPNPayService::setEnv($request->mode);
            $header = array(
                'On-Key: ' . $config['secretKey'],
                'On-Token: ' . $config['token'],
                'On-Signature: ' . hash_hmac('sha512',  $config['secretKey'] . json_encode($params), $config['token']),
                'Accept: application/json',
                'Content-Type: application/json'
            );
            $req['header']  = json_encode($header);
            LogHelper::sendLog(
                'Request Order SPNPay',
                $req,
                $project->id,
                'request_order_spnpay'
            );
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $config['url'] . '/virtual-account',
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

            $order->response        = json_encode($response);
            $order->url             = $response->paymentUrl ?? '';
            $order->save();

            $msg                    = "Success Create Order SPNPay";
            $result['link']         = $response->paymentUrl ?? '';
            $result['result']       = $response;
            DB::commit();
            return ResponseHelper::successResponse($result, $msg);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getFile(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
