<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\RequestHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Entity\Order;
use App\Model\Entity\Project;
use App\Services\Payment\OrderService;
use App\Services\System\ProjectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Http\JsonResponse;

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
        return ResponseHelper::successResponse(Order::query()->findOrFail($id));
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
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine(), $ex->getFile());
        }
    }

    public function resendCallback(int|string $id): JsonResponse
    {
        try {
            /** @var Order $order */
            $order = Order::query()->findOrFail($id);
            $status = $order->getStatus();
            if (! $status?->isSuccess()) {
                throw new Exception('Only successful orders can resend callback.');
            }

            $project = $order->project ?: Project::query()->where('type', $order->type)->first();
            if (! $project || ! $project->value || ! $project->callback) {
                throw new Exception('Project callback configuration is incomplete.');
            }

            $referenceParts = explode('-', (string) $order->reference);
            array_shift($referenceParts);

            RequestHelper::sendCallback(
                $project->value,
                [
                    'merchantOrderId' => implode('-', $referenceParts),
                    'paymentCode' => $order->payment_method,
                    'resultCode' => '00',
                ],
                $project->callback,
            );

            return ResponseHelper::successResponse(null, 'Callback resent for '.$order->reference.'.');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
