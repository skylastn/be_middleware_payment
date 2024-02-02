<?php

namespace App\Services;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\DB;

class DuitkuService
{
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
            // $createInvoice = Pop::createInvoice($params, $duitkuConfig);
            // $response = json_decode($createInvoice);

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
}
