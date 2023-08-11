<?php

namespace App\Services;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\Project;
use App\Models\Setting;
use Duitku\Config;
use Duitku\Pop;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class DuitkuService
{
    static function setEnv(string $mode): Config
    {
        $merchantKey = '';
        $merchantCode = '';
        if ($mode == 'prod') {
            $merchantKey = Setting::where('key', 'duitku_mk_prod')->first()->value ?? '';
            $merchantCode = Setting::where('key', 'duitku_mk_prod')->first()->value ?? '';
            $duitkuConfig = new Config($merchantKey, $merchantCode);
            $duitkuConfig->setSandboxMode(false);
            // set log parameter (default : true)
            $duitkuConfig->setDuitkuLogs(false);
        } else {
            $merchantKey = Setting::where('key', 'duitku_mk_sandbox')->first()->value ?? '';
            $merchantCode = Setting::where('key', 'duitku_mk_sandbox')->first()->value ?? '';
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
        $paymentAmount      = 10000; // Amount
        $email              = "customer@gmail.com"; // your customer email
        $phoneNumber        = "081234567890"; // your customer phone number (optional)
        $productDetails     = "Test Payment";
        $merchantOrderId    = time(); // from merchant, unique   
        $additionalParam    = ''; // optional
        $merchantUserInfo   = ''; // optional
        $customerVaName     = 'John Doe'; // display name on bank confirmation display
        $callbackUrl        = 'http://YOUR_SERVER/callback'; // url for callback
        $returnUrl          = 'http://YOUR_SERVER/return'; // url for redirect
        $expiryPeriod       = 60; // set the expired time in minutes

        // Customer Detail
        $firstName          = "John";
        $lastName           = "Doe";

        // Address
        $alamat             = "Jl. Kembangan Raya";
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

        try {

            DB::beginTransaction();

            $invID = DB::table('orders')->whereDate('created_at', $date)->orderBy('created_at', 'desc')->first();
            $invIDCount                         = substr($invID->id ?? 00000, -5);
            $invID_num                          = (int)$invIDCount + 1;
            $merchantOrderId                    = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);

            $req['id']                          = $merchantOrderId;
            $req['reference']                   = $project['data']->type . '-' . $request->merchantOrderId;
            $req['type']                        = $project['data']->type;
            $req['mode']                        = $request->mode ?? "sandbox";
            $req['payment_method']              = "";

            $transactionDetails['order_id']     = $req['reference'] ?? $project['data']->type . '-' . $req['id'];
            $transactionDetails['gross_amount'] = $request->paymentAmount ?? 0;
            $creditCard['secure']               = true;
            $customerDetails['first_name']      = $request->firstName ?? "";
            $customerDetails['last_name']       = $request->lastName ?? "";
            $customerDetails['email']           = $request->email ?? "xfit.id@gmail.com";
            $customerDetails['phone']           = $request->phone ?? "081512356123";

            $params = [
                "transaction_details"           => $transactionDetails,
                "credit_card"                   => $creditCard,
                "customer_details"              => $customerDetails,
            ];

            $req['request']                     = json_encode($params);
            $order                              = Order::create($req);

            $dataLog['key'] = "request_order";
            $dataLog['name'] = $req['request'];
            LogHelper::sendLog(
                'Request Order Duitku',
                json_encode($order),
                $project['data']->id,
                'request_order_duitku'
            );
            // if ($request->mode == "sandbox") {
            //     \Midtrans\Config::$isProduction   = false;
            //     \Midtrans\Config::$serverKey      = Setting::where("key", "serverkey_sandbox")->first()->value;
            // }
            // if ($request->mode == "prod") {
            //     \Midtrans\Config::$isProduction   = true;
            //     \Midtrans\Config::$serverKey      = Setting::where("key", "serverkey_prod")->first()->value;
            // }

            // $createInvoice                      = \Midtrans\Snap::getSnapToken($params);
            $createInvoice                      = $this->createTransactionMidtrans($params, $request->mode);
            $result = json_encode($createInvoice);
            LogHelper::sendLog(
                'Response Order Duitku',
                json_encode($createInvoice),
                $project['data']->id,
                'response_order_duitku'
            );
            if ($createInvoice['statusCode'] != 201) {
                return response()->json([
                    "message" => $createInvoice['response']->error_messages[0],
                ], $createInvoice['statusCode']);
            }
            DB::table('orders')
                ->where('id', $merchantOrderId)
                ->limit(1)
                ->update(
                    [
                        'response'      => $result,
                        'updated_at'    => $dateNow,
                        "url"           => $createInvoice['response']->redirect_url
                    ]
                );

            DB::commit();
            $response['message']    = "Success Create Order";
            $response['link']       = $createInvoice['response']->redirect_url;
            $response['data']       = $createInvoice;
            $duitkuConfig = DuitkuService::setEnv($request->mode);
            // createInvoice Request
            $responseDuitkuPop = Pop::createInvoice($params, $duitkuConfig);
            header('Content-Type: application/json');
            return $responseDuitkuPop;
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
