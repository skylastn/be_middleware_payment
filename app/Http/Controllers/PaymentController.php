<?php

namespace App\Http\Controllers;

use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use App\Models\Project;
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
            $order = Order::where("reference", $request->merchantRef)->orderBy('id', 'DESC')->first();

            if (empty($order)) {
                return response()->json([
                    "message" => "Order not found"
                ], 200);
            }
            
            $order->callback = json_encode($request->all());
            $status = 'PENDING';
            $resultCode = '00';
            switch ($request->status) {
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
            if (empty($status)) {
                return response()->json([
                    "message" => "Status Undefined"
                ], 403);
            }

            $order->callback        = json_encode($request->all());
            $order->status          = $status;
            $order->save();

            $paymentMethod          = PaymentMethod::where("value", $order->payment_method)->first();

            if (empty($paymentMethod)) {
                return response()->json([
                    "message" => "Payment not found"
                ], 200);
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
