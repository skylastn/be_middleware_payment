<?php

namespace App\Services;

use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\Setting;
use Duitku\Config;
use Duitku\Pop;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuitkuService
{
    static function setEnv(string $mode): Config
    {
        $merchantKey = '';
        $merchantCode = '';
        if ($mode == 'prod') {
            $merchantKey = Setting::where('key', 'duitku_mk_prod')->first()->value ?? '';
            $merchantCode = Setting::where('key', 'duitku_mc_prod')->first()->value ?? '';
            $duitkuConfig = new Config($merchantKey, $merchantCode);
            $duitkuConfig->setSandboxMode(false);
            // set log parameter (default : true)
            $duitkuConfig->setDuitkuLogs(false);
        } else {
            $merchantKey = Setting::where('key', 'duitku_mk_sandbox')->first()->value ?? '';
            $merchantCode = Setting::where('key', 'duitku_mc_sandbox')->first()->value ?? '';
            // throw new Exception($merchantCode . ' : ' . $merchantKey);
            $duitkuConfig = new Config($merchantKey, $merchantCode);
            $duitkuConfig->setSandboxMode(true);
            // set log parameter (default : true)
            $duitkuConfig->setDuitkuLogs(true);
        }
        // set sanitizer (default : true)
        // $duitkuConfig->setSanitizedMode(false);
        return $duitkuConfig;
    }

    static function orderDuitku(Request $request, Project $project)
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

            $paymentAmount      = $request->paymentAmount; // Amount
            $email              = $request->email ?? "admin@ngudek.com"; // your customer email
            $phoneNumber        = $request->phone ?? "081512356123"; // your customer phone number (optional)
            $productDetails     = $request->productDetails;
            $merchantOrderId    = $req['reference'] ?? $project->type . '-' . $req['id']; // from merchant, unique   
            $additionalParam    = ''; // optional
            $merchantUserInfo   = ''; // optional
            $customerVaName     = $request->firstName ?? ""; // display name on bank confirmation display
            $callbackUrl        = env('APP_URL') . '/api/callbackDuitku'; // url for callback
            $returnUrl          = env('APP_URL') . '/api/callbackDuitku'; // url for redirect
            $expiryPeriod       = $request->expiryPeriod ?? 180; // set the expired time in minutes

            // Customer Detail
            $firstName          = $request->firstName ?? "";
            $lastName           = $request->lastName ?? "";

            // Address
            $alamat             = $request->address;
            $city               = "Jakarta";
            $postalCode         = "11530";
            $countryCode        = "ID";

            $address = array(
                'firstName'     => $firstName,
                'lastName'      => $lastName,
                'address'       => $alamat,
                'city'          => $city,
                'postalCode'    => $postalCode,
                'phone'         => $phoneNumber,
                'countryCode'   => $countryCode
            );

            $customerDetail = array(
                'firstName'         => $firstName,
                'lastName'          => $lastName,
                'email'             => $email,
                'phoneNumber'       => $phoneNumber,
                'billingAddress'    => $address,
                'shippingAddress'   => $address
            );

            // Item Details
            $item1 = array(
                'name'      => $productDetails,
                'price'     => $paymentAmount,
                'quantity'  => 1
            );

            $itemDetails = array(
                $item1
            );

            $params = array(
                'paymentAmount'     => $paymentAmount,
                'merchantOrderId'   => $merchantOrderId,
                'productDetails'    => $productDetails,
                'additionalParam'   => $additionalParam,
                'merchantUserInfo'  => $merchantUserInfo,
                'customerVaName'    => $customerVaName,
                'email'             => $email,
                'phoneNumber'       => $phoneNumber,
                'itemDetails'       => $itemDetails,
                'customerDetail'    => $customerDetail,
                'callbackUrl'       => $callbackUrl,
                'returnUrl'         => $returnUrl,
                'expiryPeriod'      => $expiryPeriod
            );

            $req['request']         = json_encode($params);
            $order                  = Order::create($req);

            LogHelper::sendLog(
                'Request Order Duitku',
                $req,
                $project->id,
                'request_order_duitku'
            );

            $duitkuConfig = DuitkuService::setEnv($request->mode);
            // createInvoice Request
            $createInvoice = Pop::createInvoice($params, $duitkuConfig);
            $response = json_decode($createInvoice);

            LogHelper::sendLog(
                'Response Order Duitku',
                $response,
                $project->id,
                'response_order_duitku'
            );
            $order->response        = json_encode($response);
            $order->url             = $response->paymentUrl;
            $order->save();

            $msg                    = "Success Create Order Duitku";
            $result['link']         = $response->paymentUrl;
            $result['result']       = $response;
            DB::commit();
            return ResponseHelper::successResponse($result, $msg);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getFile(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public static function callback(Order $order, Request $request)
    {
        try {
            DB::beginTransaction();

            $duitkuConfig = DuitkuService::setEnv($order->mode);
            $callback = Pop::callback($duitkuConfig);
            header('Content-Type: application/json');
            $notif = json_decode($callback);

            // var_dump($callback);
            $status = '';
            if ($notif->resultCode == "00") {
                $status = 'SUCCESS';
            } else if ($notif->resultCode == "01") {
                $status = 'FAILED';
            }

            if (empty($status)) {
                throw new Exception("Status Undefined : $status");
            }
            $reference              = $request->merchantOrderId;
            $order->callback        = json_encode($request->all());
            $order->status          = $status;
            $paymentMethod          = PaymentMethod::where("key", $request->paymentCode)->first();

            if (empty($paymentMethod)) {
                throw new Exception('Payment not found : ' . $request->paymentCode);
            }

            $order->payment_method  = $paymentMethod->value;
            $order->save();

            $split              = explode("-", $reference);
            $project            = Project::where("type", $split[0])->first();
            if (empty($project)) {
                throw new Exception('Project Not Found');
            }
            LogHelper::sendLog(
                'Callback Duitku',
                json_encode($order->callback),
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
            $params['merchantOrderId']  = $idSend;
            $params['paymentCode']      = $order->payment_method;
            $params['resultCode']       = $notif->resultCode;
            $callback                   = RequestHelper::sendCallback($project->value, $params, $project->callback);

            $response['message']    = "Success Send Callback";
            $response['data']       = $callback;
            DB::commit();
            return ResponseHelper::successResponse($response, $response['message']);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public static function syncPaymentDuitku()
    // : Collection
    {
        $duitkuConfig = DuitkuService::setEnv('sandbox');
        $paymentAmount = "10000"; //"YOUR_AMOUNT";
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
            if (!$check) {
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
}
