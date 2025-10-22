<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Helper\FormatHelper;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\OrderService;
use App\Services\ProjectService;
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

    public function index(Request $request)
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

    public function detail(Request $request)
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
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
