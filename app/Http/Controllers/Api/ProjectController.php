<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Services\System\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ProjectController extends Controller
{
    private ProjectService $projectService;
    public function __construct()
    {
        $this->projectService = new ProjectService();
    }

    public function index(Request $request): JsonResponse
    {
        return ResponseHelper::formatPagination($this->projectService->getListProject($request));
    }

    public function show(int|string $id): JsonResponse
    {
        return ResponseHelper::successResponse($this->projectService->getProjectById($id));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $insert = $this->projectService->createWithLog($request);

            return ResponseHelper::successResponse($insert, "Success Create Project");
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        try {

            DB::beginTransaction();
            $project = $this->projectService->update($request, $id);
            DB::commit();
            return ResponseHelper::successResponse($project);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function delete(int|string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $project = $this->projectService->delete($id);
            DB::commit();
            return ResponseHelper::successResponse($project);
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function syncMissingLog(): JsonResponse
    {
        try {
            return ResponseHelper::successResponse(
                $this->projectService->syncMissingLogTables(),
                'Success Sync Missing Project Logs',
            );
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }
}
