<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\Payment\OrderService;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    private OrderService $service;
    private ProjectService $projectService;
    public function __construct()
    {
        $this->service = new OrderService();
        $this->projectService = new ProjectService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return ResponseHelper::formatPagination($this->service->getListOrder($request));
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function detail(Request $request): JsonResponse
    {
        try {
            $project    = $this->projectService->checkKey();
            $response   = $this->service->detailByReferenceAndKey($request->reference, $project->type);
            if (!FormatHelper::isNotEmpty($response)) {
                throw new Exception("Unknown Order", 400);
            }
            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            $error['file']      = $ex->getFile();
            Log::error($error);
            return ResponseHelper::failedResponse($ex->getMessage());
        }
    }

    public function show(int|string $id): JsonResponse
    {
        $order = $this->service->getOrderById($id);
        if (! $order) {
            return ResponseHelper::failedResponse('Order Not Found', 'Order Not Found', 404);
        }

        return ResponseHelper::successResponse($order);
    }

    public function checkOrderStatus(Request $request): JsonResponse
    {
        try {
            $result = $this->service->checkOrderStatus($request->reference);
            return ResponseHelper::successResponse($result);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $response = $this->service->create($request);
            DB::commit();
            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);
            if ($ex->getMessage() === 'Unauthorized') {
                return ResponseHelper::unauthorizedResponse($ex->getMessage());
            }
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function confirmStripe(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $response = $this->service->confirmStripe($request);
            DB::commit();
            return ResponseHelper::successResponse($response);
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    // Note on Stripe confirm errors:
    // The service now logs then re-throws ("throw again the log") for CardException and ApiErrorException.
    // Controller's catch does the rollback + failedResponse.
    // Status is only updated to FAILED in the service for payment failed cases (CardException); other errors
    // do not update order status here (webhooks or checkStatus can sync later).

    public function resendCallback(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $order = $this->service->resendCallbackById($id);
            DB::commit();

            return ResponseHelper::successResponse(null, 'Callback resent for '.$order->reference.'.');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function setSuccessAdmin(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $updatedOrder = $this->service->setSuccessById($id);
            DB::commit();

            return ResponseHelper::successResponse($updatedOrder, 'Order status updated to SUCCESS and callback sent.');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function setSuccessMerchant(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $project = $request->attributes->get('project') ?? $this->projectService->checkKey();
            $reference = $request->input('reference') ?? $request->input('merchantOrderId');

            if (! $reference) {
                throw new Exception('reference or merchantOrderId parameter is required', 400);
            }

            $order = $this->service->detailByReferenceAndKey($reference, $project->type);
            if (! $order) {
                // Try searching with prefix {type}-{reference}
                $order = $this->service->detailByReferenceAndKey($project->type.'-'.$reference, $project->type);
            }

            if (! $order) {
                throw new Exception('Order Not Found', 404);
            }

            $updatedOrder = $this->service->markAsSuccessAndSendCallback($order, $project);

            DB::commit();

            return ResponseHelper::successResponse($updatedOrder, 'Order marked as SUCCESS and webhook callback sent.');
        } catch (Exception $ex) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }
}
