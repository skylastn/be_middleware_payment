<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\System\QueueMonitorService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQueueController extends Controller
{
    private QueueMonitorService $queueService;

    public function __construct(?QueueMonitorService $queueService = null)
    {
        $this->queueService = $queueService ?? new QueueMonitorService;
    }

    public function overview(): JsonResponse
    {
        try {
            $data = $this->queueService->getOverview();

            return ResponseHelper::successResponse($data, 'Success Get Queue Overview');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to get queue overview', 500);
        }
    }

    public function activeJobs(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type', 'all');
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 20);

            $jobs = $this->queueService->getActiveJobs($type, $perPage, $page);

            return ResponseHelper::formatPagination($jobs);
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to get active queue jobs', 500);
        }
    }

    public function failedJobs(Request $request): JsonResponse
    {
        try {
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 20);
            $search = $request->query('search');

            $jobs = $this->queueService->getFailedJobs($perPage, $page, $search);

            return ResponseHelper::formatPagination($jobs);
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to get failed queue jobs', 500);
        }
    }

    public function retryFailedJob(int|string $id): JsonResponse
    {
        try {
            $result = $this->queueService->retryFailedJob($id);

            return ResponseHelper::successResponse($result, 'Job retry initiated');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to retry job', 500);
        }
    }

    public function retryAllFailedJobs(): JsonResponse
    {
        try {
            $result = $this->queueService->retryAllFailedJobs();

            return ResponseHelper::successResponse($result, 'All failed jobs retry initiated');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to retry all jobs', 500);
        }
    }

    public function forgetFailedJob(int|string $id): JsonResponse
    {
        try {
            $result = $this->queueService->forgetFailedJob($id);

            return ResponseHelper::successResponse($result, 'Failed job deleted');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to delete failed job', 500);
        }
    }

    public function flushFailedJobs(): JsonResponse
    {
        try {
            $result = $this->queueService->flushFailedJobs();

            return ResponseHelper::successResponse($result, 'All failed jobs flushed');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to flush jobs', 500);
        }
    }

    public function testDispatch(Request $request): JsonResponse
    {
        try {
            $message = (string) $request->input('message', 'Test job dispatched from Backoffice at '.now()->toDateTimeString());
            $delay = (int) $request->input('delay_seconds', 0);

            $result = $this->queueService->dispatchTestJob($message, $delay);

            return ResponseHelper::successResponse($result, 'Test job successfully dispatched');
        } catch (Exception $e) {
            LogHelper::sendErrorLog($e);

            return ResponseHelper::failedResponse($e->getMessage(), 'Failed to dispatch test job', 500);
        }
    }
}
