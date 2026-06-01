<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Http\Helper\LogHelper;
use App\Http\Helper\ResponseHelper;
use App\Model\Entity\Project;
use App\Services\System\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class ProjectController extends Controller
{
    private ProjectService $projectService;
    public function __construct()
    {
        $this->projectService = new ProjectService();
    }

    public function index(Request $request)
    {
        return ResponseHelper::formatPagination($this->projectService->getListProject($request));
    }

    public function show($id)
    {
        return ResponseHelper::successResponse($this->projectService->getProjectById($id));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $insert = $this->projectService->create($request);
            DB::commit();
            Schema::create('log__' . $insert->id, function (Blueprint $table) {
                $table->increments('id');
                $table->string('key');
                $table->text('value');
                $table->text('ip');
                $table->timestamps();
            });
            return ResponseHelper::successResponse($insert, "Success Create Project");
        } catch (Exception $ex) {
            DB::rollback();
            LogHelper::sendErrorLog($ex);
            return ResponseHelper::failedResponse($ex->getMessage(), $ex->getMessage(), 400, $ex->getLine());
        }
    }

    public function update(Request $request, $id)
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

    public function delete($id)
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
}
