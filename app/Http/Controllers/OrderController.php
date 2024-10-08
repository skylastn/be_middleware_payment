<?php

namespace App\Http\Controllers;
// namespace Midtrans;

use Midtrans\Midtrans;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\ProjectController;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Project;
use App\Models\Setting;
use App\Models\PaymentMethod;
use App\Services\DuitkuService;
use App\Services\SPNPayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Duitku\Pop;
use Xendit\Xendit;
use Exception;
use Midtrans\Config;
use Midtrans\Notification;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $cek        = (new ProjectController)->checkKey();
            $page       = $request->page ?? "0";
            $limit      = $request->limit ?? "10";
            $response   = Order::forPage($page, $limit)->where('type', $cek->type)->latest()->get();
            return ResponseHelper::successResponse($response);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function detail(Request $request)
    {
        try {
            if (env('PAYMENT_APP_KEY') != request()->header('Key')) {
                throw new Exception("Unauthorized", 403);
            }
            $response   = Order::where('reference', $request->reference)->latest()->first();
            if(empty($response)){
                throw new Exception("Unknown Order", 400);
            }
            return ResponseHelper::successResponse($response);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $project        = (new ProjectController)->checkKey();
            if ($project->slug == "xendit") {
                return $this->orderXendit($request, $project);
            }
            if ($project->slug == "midtrans") {
                return $this->orderMidtrans($request, $project);
            }
            if ($project->slug == "duitku") {
                return DuitkuService::orderDuitku($request, $project);
            }
            if ($project->slug == "spnpay") {
                return SPNPayService::createOrderSPNPay($request, $project);
                // return SPNPayService::sendCurlRequest();
            }
            $response['message']    = "Undefined Project";
            return response()->json($response, 403);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function createPayment(Request $request)
    {
        try {
            if (env('PAYMENT_APP_KEY') != request()->header('Key')) {
                throw new Exception("Unauthorized", 403);
            }
            $order                  = Order::where('reference', $request->reference)->latest()->first();
            if (empty($order)) {
                throw new Exception("Order Not Found", 403);
            }
            $project = Project::where('type', $order->type)->first();
            if (empty($project)) {
                throw new Exception("Project Not Found", 403);
            }
            // if ($project->slug == "xendit") {
            //     return $this->orderXendit($request, $project);
            // }
            // if ($project->slug == "midtrans") {
            //     return $this->orderMidtrans($request, $project);
            // }
            // if ($project->slug == "duitku") {
            //     return DuitkuService::orderDuitku($request, $project);
            // }
            if ($project->slug == "spnpay") {
                return SPNPayService::createOrderPaymentSPNPay($request, $project, $order);
            }
            $response['message']    = "Undefined Request";
            return response()->json($response, 403);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function orderMidtrans($request, $project)
    {
        $dateNow = date("Y-m-d H:i:s");
        $date = date("Y-m-d");
        try {
            DB::beginTransaction();

            $invID = DB::table('orders')->whereDate('created_at', $date)->orderBy('created_at', 'desc')->first();
            $invIDCount                         = substr($invID->id ?? 00000, -5);
            $invID_num                          = (int)$invIDCount + 1;
            $merchantOrderId                    = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);

            $req['id']                          = $merchantOrderId;
            $req['reference']                   = $project->type . '-' . $request->merchantOrderId;
            $req['type']                        = $project->type;
            $req['mode']                        = $request->mode ?? "sandbox";
            $req['payment_method']              = "";

            $transactionDetails['order_id']     = $req['reference'] ?? $project->type . '-' . $req['id'];
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
            LogHelper::sendLog(
                'Request Order Midtrans',
                json_encode($order),
                $project->id,
                'request_order_midtrans'
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
                'Response Order Midtrans',
                json_encode($createInvoice),
                $project->id,
                'response_order_midtrans'
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
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function orderXendit($request, $project)
    {
        try {
            $dateNow = date("Y-m-d H:i:s");
            DB::beginTransaction();
            $secretKey = Setting::where("key", "xendit_secretkey_sandbox")->first()->value;
            if ($request->mode == "prod") {
                $secretKey = Setting::where("key", "xendit_secretkey_prod")->first()->value;
            }
            $urlSuccess = Setting::where("key", "url_success")->first()->value;
            Xendit::setApiKey($secretKey);

            $invID = DB::table('orders')->whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc')->first();
            $invIDCount                 = substr($invID->id ?? 00000, -5);
            $invID_num                  = (int)$invIDCount + 1;
            $merchantOrderId            = date("Ymd") . "-" . str_pad($invID_num, 5, '0', STR_PAD_LEFT);
            // return $request->req;
            $req['id']                  = $merchantOrderId;
            $req['reference']           = $project->type . '-' . $request->merchantOrderId;
            $req['type']                = $project->type;
            $req['mode']                = $request->mode ?? "sandbox";
            $req['payment_method']      = "";

            $expired                    = ($request->expiryPeriod ?? 0) * 60;
            // return $request;
            $params = [
                'external_id' => $req['reference'] ?? $project->type . '-' . $req['id'],
                'amount' => $request->paymentAmount ?? 0,
                'description' => $request->productDetails ?? "Payment",
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
                //         'url' => $cek['data']->callback
                //     ]
                // ],
                // 'fees' => [
                //     [
                //         'type' => 'ADMIN',
                //         'value' => 5000
                //     ]
                // ]
            ];

            $req['request']             = json_encode($params);
            $order                      = Order::create($req);
            LogHelper::sendLog(
                'Request Order Xendit',
                json_encode($order),
                $project->id,
                'request_order_xendit'
            );

            $createInvoice = \Xendit\Invoice::create($params);
            $result = json_encode($createInvoice);
            LogHelper::sendLog(
                'Response Order Xendit',
                json_encode($createInvoice),
                $project->id,
                'response_order_xendit'
            );
            DB::table('orders')
                ->where('id', $merchantOrderId)
                ->limit(1)
                ->update(
                    [
                        'response'      => $result,
                        'updated_at'    => $dateNow,
                        "url"           => $createInvoice['invoice_url']
                    ]
                );

            DB::commit();
            $response['message']    = "Success Create Order";
            $response['link']       = $createInvoice['invoice_url'];
            $response['data']       = $createInvoice;
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function createTransactionMidtrans($body, $mode)
    {
        $curl = curl_init();
        $urlOrderMidtrans   = "";
        $serverKey          = "";
        if ($mode == "sandbox") {
            $urlOrderMidtrans   = Setting::where("key", "url_sandbox_ordermidtrans")->first()->value;
            $serverKey          = Setting::where("key", "serverkey_sandbox")->first()->value;
        }
        if ($mode == "prod") {
            $urlOrderMidtrans   = Setting::where("key", "url_prod_ordermidtrans")->first()->value;
            $serverKey          = Setting::where("key", "serverkey_prod")->first()->value;
        }
        $serverKey = base64_encode($serverKey . ":");

        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL => $urlOrderMidtrans,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Basic $serverKey",
                    'Content-Type: application/json'
                ),
            ),
        );

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $result['response']     = json_decode($response);
        $result['statusCode']   = $httpcode;
        return $result;
    }

    public function Callback(Request $request)
    {
        try {
            DB::beginTransaction();
            $order = Order::where("reference", $request->external_id)->orderBy('id', 'DESC')->first();
            if (!$order) {
                return response()->json([
                    "message" => "Order not found"
                ], 200);
            }

            $xenditToken = Setting::where("key", "xendit_tokencallback_sandbox")->first()->value;
            if ($order->mode == "prod") {
                $xenditToken = Setting::where("key", "xendit_tokencallback")->first()->value;
            }
            $reqHeaders = getallheaders();
            $incomingTokenXendit = isset($reqHeaders['X-Callback-Token']) ? $reqHeaders['X-Callback-Token'] : "";

            if ($xenditToken != $incomingTokenXendit) {
                return response()->json([
                    "message" => "You are not permitted perform this"
                ], 403);
            }


            $order->callback        = json_encode($request->all());
            $order->status          = $request->status;

            $order->payment_method  = $request->payment_channel;

            $order->save();

            $split              = explode("-", $request->external_id);
            $project            = Project::where("type", $split[0])->first();
            LogHelper::sendLog(
                'Callback Midtrans',
                json_encode($request->all()),
                $project->id,
                'callback_order_xendit'
            );
            if ($request->status == "PAID") {
                $params['merchantOrderId']  = $split[1] . "-" . $split[2];
                $params['paymentCode']      = $order->payment_method;
                $params['resultCode']       = "00";
                $callback                   = RequestHelper::sendCallback($project->value, $params, $project->callback);
            }

            DB::commit();
            $response['message']    = "Success Send Callback";
            $response['data']       = $callback;

            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function callbackMidtrans(Request $request)
    {
        try {

            DB::beginTransaction();

            $order = Order::where("reference", $request->order_id)->orderBy('id', 'DESC')->first();

            if (!$order) {
                return response()->json([
                    "message" => "Order not found"
                ], 200);
            }

            if ($order->mode == "sandbox") {
                Config::$isProduction   = false;
                Config::$serverKey      = Setting::where("key", "serverkey_sandbox")->first()->value;
            }
            if ($order->mode == "prod") {
                Config::$isProduction   = true;
                Config::$serverKey      = Setting::where("key", "serverkey_prod")->first()->value;
            }

            $notifs =  new Notification();
            $notif = $notifs->getResponse();
            $transaction = $notif->transaction_status;
            $type = $notif->payment_type;
            $reference = $notif->order_id;
            $fraud = $notif->fraud_status;
            $status = "";
            if ($transaction == 'capture') {
                // For credit card transaction, we need to check whether transaction is challenge by FDS or not
                if ($type == 'credit_card') {
                    if ($fraud == 'challenge') {
                        // TODO set payment status in merchant's database to 'Challenge by FDS'
                        // TODO merchant should decide whether this transaction is authorized or not in MAP
                        // echo "Transaction order_id: " . $order_id ." is challenged by FDS";
                        $status = strtoupper($transaction);
                    } else {
                        // TODO set payment status in merchant's database to 'Success'
                        // echo "Transaction order_id: " . $order_id ." successfully captured using " . $type;
                        $status = "PAID";
                    }
                }
            } else if ($transaction == 'settlement') {
                // TODO set payment status in merchant's database to 'Settlement'
                // echo "Transaction order_id: " . $order_id ." successfully transfered using " . $type;
                $status = "PAID";
            } else if ($transaction == 'pending') {
                // TODO set payment status in merchant's database to 'Pending'
                // echo "Waiting customer to finish transaction order_id: " . $order_id . " using " . $type;
                $status = strtoupper($transaction);
            } else if ($transaction == 'deny') {
                // TODO set payment status in merchant's database to 'Denied'
                // echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is denied.";
                $status = strtoupper($transaction);
            } else if ($transaction == 'expire') {
                // TODO set payment status in merchant's database to 'expire'
                // echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is expired.";
                $status = strtoupper($transaction);
            } else if ($transaction == 'cancel') {
                // TODO set payment status in merchant's database to 'Denied'
                // echo "Payment using " . $type . " for transaction order_id: " . $order_id . " is canceled.";
                $status = strtoupper($transaction);
            }

            if (empty($status)) {
                return response()->json([
                    "message" => "Status Undefined"
                ], 403);
            }

            if ($status != "PAID") {
                return response()->json([
                    "message" => "Status $status"
                ], 200);
            }


            $order->callback        = json_encode($request->all());
            $order->status          = $status;

            if ($type == "bank_transfer") {
                if (empty($request->bank) || $request->bank == "permata") {
                    $paymentMethod          = PaymentMethod::where("key", $type)->where("type", "permata")->first();
                } else {
                    $paymentMethod          = PaymentMethod::where("key", $type)->where("type", $request->bank)->first();
                }
            } else {
                $paymentMethod          = PaymentMethod::where("key", $type)->first();
            }

            if (empty($paymentMethod)) {
                return response()->json([
                    "message" => "Payment not found"
                ], 200);
            }

            $order->payment_method  = $paymentMethod->value;
            $order->save();

            $split              = explode("-", $reference);
            $project            = Project::where("type", $split[0])->first();
            LogHelper::sendLog(
                'Callback Midtrans',
                json_encode($order->callback),
                $project->id,
                'callback_order_midtrans'
            );
            $params['merchantOrderId']  = $split[1] . "-" . $split[2];
            $params['paymentCode']      = $order->payment_method;
            $params['resultCode']       = "00";
            $callback                   = RequestHelper::sendCallback($project->value, $params, $project->callback);

            DB::commit();
            $response['message']    = "Success Send Callback";
            $response['data']       = $callback;

            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function callbackDuitku(Request $request)
    {
        try {
            LogHelper::sendLog('callback', $request->all());
            $now                = Carbon::now();
            $dateBefore         = date("Y-m-d", strtotime("-1 week"));
            $order = Order::where("reference", $request->merchantOrderId)
                ->whereBetween('created_at', [$dateBefore, $now])
                ->orderBy('id', 'DESC')->first();
            if (!$order) {
                throw new Exception('Order not found');
            }
            return DuitkuService::callback($order, $request);
        } catch (Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($error, $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
