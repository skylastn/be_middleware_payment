<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $result = $this->paymentService->createPayment($request);
            DB::commit();

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            DB::rollBack();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
