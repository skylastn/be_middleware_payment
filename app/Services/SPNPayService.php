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
//             $params = '{
//     "paymentAmount": 15000,
//     "paymentMethod": "",
//     "merchantOrderId": "20220912-00044",
//     "productDetails": "Pembayaran SPNPay",
//     "expiryPeriod": 10,
//     "mode" : "sandbox",
//     "firstName" : "Sahid",
//     "lastName" : "R",
//     "email" : "sahidrahutomo@gmail.com",
//     "address" : "klaten",
//     "phone" : "08815123766"
// }';
            $config = SPNPayService::setEnv($request->mode);
            throw new Exception(json_encode($config));
            $url = $config['url'] . '/virtual-account';
            $signature = hash_hmac('sha512',  $config['secretKey'] . json_encode($params), $config['token']);
            $header = array(
                'On-Key: ' . $config['secretKey'],
                'On-Token: ' . $config['token'],
                'On-Signature: ' . $signature,
                'Accept: application/json',
                'Content-Type: application/json'
            );
            $req['header']  = json_encode($header);
            $req['url']  = $url;

            LogHelper::sendLog(
                'Request Order SPNPay',
                $req,
                $project->id,
                'request_order_spnpay'
            );

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

    static function sendCurlRequest()
    {
        $url = 'https://api.sandbox.cronosengine.com/api/qris';
        $key = 'SC-3DEIIWDRNN77W6MG'; // sandbox
        $token = '8qJKU9FA1Y8uBpLsWU3cRg1n0uh8rGLy';

        $codeSignature = hash_hmac('sha512', $key, $token);
        $data = json_encode([
            'reference' => 'qrisstestCRONOS2-MPAY' . time(),
            'amount' => 10000,
            'expiryMinutes' => 30,
            'viewName' => 'Antzyn',
            'additionalInfo' => [
                'callback' => 'https://kraken.free.beeceptor.com/notify',
            ],
        ]);
        $codeSignature = hash_hmac('sha512', $key . $data, $token);
        $headers = [
            "On-Key: $key",
            "On-Token: $token",
            "On-Signature: " . $codeSignature,
            "Content-Type: application/json",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

        $response = json_decode(curl_exec($ch));
        curl_close($ch);

        $imageUrl = $response->responseData->qris->image;
        $content = $response->responseData->qris->content;

        echo "<div style='text-align: center; margin-top: 150px;'>";
        echo "<img src='$imageUrl' alt='QR Code' />";
        echo "$content";
        echo "<br><br>";

        var_dump($response);
    }
}
