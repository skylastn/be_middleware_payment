<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Request\Project\CreateProjectRequest;
use App\Model\Request\Project\UpdateProjectRequest;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    private ProjectService $projectService;
    public function __construct()
    {
        $this->projectService = new ProjectService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return ResponseHelper::formatPagination($this->projectService->getListProject($request));
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            if (stripos($ex->getMessage(), 'unauthorized') !== false) {
                return ResponseHelper::unauthorizedResponse($ex->getMessage());
            }
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function show(int|string $id): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectById($id);
            if (! $project) {
                return ResponseHelper::failedResponse('Project not found', 'Project not found', 404);
            }
            return ResponseHelper::successResponse($project);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            if (stripos($ex->getMessage(), 'unauthorized') !== false) {
                return ResponseHelper::unauthorizedResponse($ex->getMessage());
            }
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        try {
            $insert = $this->projectService->createWithLog($request);

            return ResponseHelper::successResponse($insert, "Success Create Project");
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function update(UpdateProjectRequest $request, int|string $id): JsonResponse
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

    public function logs(Request $request, int|string $id): JsonResponse
    {
        try {
            $logs = $this->projectService->getProjectLogs($id, $request);

            return ResponseHelper::formatPagination($logs);
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function logKeys(int|string $id): JsonResponse
    {
        try {
            $keys = $this->projectService->getProjectLogKeys($id);

            return ResponseHelper::successResponse($keys, 'Success Get Project Log Keys');
        } catch (Exception $ex) {
            LogHelper::sendErrorLog($ex);

            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function clearLogs(int|string $id): JsonResponse
    {
        try {
            $this->projectService->clearProjectLogs($id);

            return ResponseHelper::successResponse(null, 'Success Clear Project Logs');
        } catch (Exception $ex) {
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
