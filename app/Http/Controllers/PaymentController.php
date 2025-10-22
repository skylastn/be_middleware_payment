<?php

namespace App\Http\Controllers;

use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Models\Order;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private PaymentService $paymentService;
    public function __construct()
    {
        $this->paymentService = new PaymentService();
    }

    function getPaymentCategory()
    {
        return ResponseHelper::successResponse($this->paymentService->getListPaymentCategory());
    }

    function getPaymentMethod(Request $request)
    {
        $result         = $this->paymentService->getListPaymentMethod($request);
        return ResponseHelper::successResponse($result);
    }

    function getDetailPaymentMethod(Request $request)
    {
        $value          = $request->value;
        $from           = $request->from;
        $result         = $this->paymentService->getDetailPaymentMethod($value, $from);
        return ResponseHelper::successResponse($result);
    }



    public function createPayment(Request $request)
    {
        try {
            DB::beginTransaction();
            $result = null;
            if (env('PAYMENT_APP_KEY') != request()->header('Key')) {
                throw new Exception("Unauthorized", 403);
            }
            $order                  = Order::where('reference', $request->reference)->latest()->first();
            if (!FormatHelper::isNotEmpty($order)) {
                throw new Exception("Order Not Found", 403);
            }
            $project = Project::where('type', $order->type)->first();
            if (!FormatHelper::isNotEmpty($project)) {
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
                $result = $this->paymentService->createPayment($request);
            }
            DB::commit();
            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            DB::rollBack();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
