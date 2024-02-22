<?php

namespace App\Http\Controllers;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class PaymentController extends Controller
{
    function getPaymentCategory()
    {
        return ResponseHelper::successResponse(PaymentCategory::get());
    }

    function getPaymentMethod(Request $request)
    {
        $categoriesKey  = $request->categoriesKey;
        $from           = $request->from;
        $result         = PaymentMethod::when($categoriesKey, function ($query) use ($categoriesKey) {
            return $query->whereIn('key', $categoriesKey);
        })->when($from, function ($query) use ($from) {
            return $query->where('from', $from);
        })->get();
        return ResponseHelper::successResponse($result);
    }

    function getDetailPaymentMethod(Request $request)
    {
        $value          = $request->value;
        $from           = $request->from;
        $result         = PaymentMethod::when($value, function ($query) use ($value) {
            return $query->where('value', $value);
        })->when($from, function ($query) use ($from) {
            return $query->where('from', $from);
        })->first();
        return ResponseHelper::successResponse($result);
    }

    public function callbackSPNPay(Request $request)
    {
        try {

            DB::beginTransaction();
            LogHelper::sendLog(
                'Callback SPNPay',
                json_encode($request->all()),
                '0',
                'callback_order_spnpay'
            );
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
}
