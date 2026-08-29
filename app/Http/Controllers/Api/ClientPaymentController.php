<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\OrderService;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientPaymentController extends Controller
{
    private OrderService $orderService;
    private PaymentService $paymentService;

    public function __construct()
    {
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
    }

    public function detail(Request $request): JsonResponse
    {
        try {
            $project = $request->attributes->get('project');
            $response = $this->orderService->detailByReferenceAndKey($request->reference, $project->type);

            if (! FormatHelper::isNotEmpty($response)) {
                throw new Exception('Unknown Order', 400);
            }

            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            $error['line'] = $ex->getLine();
            $error['message'] = $ex->getMessage();
            $error['file'] = $ex->getFile();
            Log::error($error);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function checkOrderStatus(Request $request): JsonResponse
    {
        try {
            $result = $this->orderService->checkOrderStatus($request->reference);

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function createPayment(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project = $request->attributes->get('project');
            $result = $this->paymentService->createPayment($request, $project);

            DB::commit();

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            DB::rollBack();
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function getPaymentCategory(): JsonResponse
    {
        return ResponseHelper::successResponse($this->paymentService->getListPaymentCategory());
    }

    public function getPaymentMethod(Request $request): JsonResponse
    {
        $result = $this->paymentService->getListPaymentMethod($request);

        return ResponseHelper::successResponse($result);
    }

    public function getDetailPaymentMethod(Request $request): JsonResponse
    {
        $result = $this->paymentService->getDetailPaymentMethod($request->value, $request->from);

        return ResponseHelper::successResponse($result);
    }
}
