<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectSlug;
use App\Http\Controllers\Controller;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Response\Payment\PaymentMethod\PaymentMethodResource;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\OrderService;
use App\Services\Payment\PaprikaService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\StripeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientPaymentController extends Controller
{
    private OrderService $orderService;
    private PaymentService $paymentService;
    private DuitkuService $duitkuService;
    private StripeService $stripeService;
    private SPNPayService $spnPayService;
    private PaprikaService $paprikaService;

    public function __construct()
    {
        $this->orderService = new OrderService();
        $this->paymentService = new PaymentService();
        $this->duitkuService = new DuitkuService();
        $this->stripeService = new StripeService();
        $this->spnPayService = new SPNPayService();
        $this->paprikaService = new PaprikaService();
    }

    public function detail(Request $request): JsonResponse
    {
        try {
            $project = $request->attributes->get('project');
            $response = $this->orderService->detailByReferenceAndKey($request->reference, $project->type);

            if (! FormatHelper::isNotEmpty($response)) {
                throw new Exception('Unknown Order', 400);
            }

            return ResponseHelper::successResponse(new \App\Model\Response\Order\OrderResource($response));
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
            $project = $request->attributes->get('project');
            $order = $this->orderService->detailByReferenceAndKey($request->reference, $project->type);
            if (! FormatHelper::isNotEmpty($order)) {
                throw new Exception('Order Not Found');
            }

            $result = match ($project->getSlug()) {
                ProjectSlug::DUITKU => $this->duitkuService->checkStatus($order),
                ProjectSlug::STRIPE => $this->stripeService->checkStatus($order),
                default => throw new Exception('Undefined Project'),
            };

            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function createPayment(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project = $request->attributes->get('project');
            $order = $this->orderService->detailByReferenceAndKey($request->reference, $project->type);
            if (! FormatHelper::isNotEmpty($order)) {
                throw new Exception('Order Not Found', 404);
            }

            $result = match ($project->getSlug()) {
                ProjectSlug::DUITKU => $this->duitkuService->createOrderPaymentDuitku($request, $project, $order),
                ProjectSlug::SPNPAY => $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order),
                ProjectSlug::STRIPE => $this->stripeService->order($request, $project),
                ProjectSlug::PAPRIKA => $this->paprikaService->orderPaprika($request, $project),
                default => throw new Exception('Undefined Project'),
            };

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
        $result = $this->paymentService->getListPaymentMethod($request, true);

        return ResponseHelper::successResponse(PaymentMethodResource::collection($result));
    }

    public function getDetailPaymentMethod(Request $request): JsonResponse
    {
        $key = $request->query('key', $request->query('value'));
        $paymentGatewayId = $request->query('payment_gateway_id', $request->query('paymentGatewayId'));
        $paymentGatewayKey = $request->query('payment_gateway_key', $request->query('paymentGatewayKey', $request->query('from')));
        $result = $this->paymentService->getDetailPaymentMethod($key, $paymentGatewayId, true, $paymentGatewayKey);

        return ResponseHelper::successResponse($result ? new PaymentMethodResource($result) : null);
    }
}
