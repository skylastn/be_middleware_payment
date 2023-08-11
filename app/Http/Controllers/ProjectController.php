<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $page       = $request->page ?? 0;
        $limit      = $request->limit ?? 10;
        $response['message'] = "Success Get Project";
        $response['data']   = Project::forPage($page, $limit)->get();
        return response()->json($response, 200);
    }

    public function show($id)
    {
        return Project::find($id);
    }

    public function checkKey()
    {
        $result['status'] = true;
        $where['value'] = request()->header('Token');
        $project        = Project::where($where)->first();
        if (!isset($project)) {
            $result['status'] = false;
            $result['message'] = "Unauthorized";
        }
        $result['data'] = $project;
        return $result;
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $data['name']       = $request->name;
            $data['type']       = $request->type;
            $data['slug']       = $request->slug;
            $data['key']        = Str::random(10);
            $data['secure']     = Str::random(20);
            $data['callback']   = $request->callback;
            $data['value']      = Str::random(60);
            $insert = Project::create($data);
            DB::commit();
            Schema::create('log__' . $insert->id, function (Blueprint $table) {
                $table->increments('id');
                $table->string('key');
                $table->text('name');
                $table->text('ip');
                $table->timestamps();
            });
            $response['message']    = "Success Create Project";
            $response['data']       = $insert;
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            DB::beginTransaction();
            $project = Project::where("value", $request->header('Token'));
            $project->update($request->all());
            $response['data']   = $project;
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            $error['line']      = $ex->getLine();
            $error['message']   = $ex->getMessage();
            Log::error($error);
            DB::rollback();
            return response()->json([
                "message" => $ex->getMessage()
            ], 400);
        }
    }

    public function delete(Request $request, $id)
    {
        $article = Project::findOrFail($id);
        $article->delete();

        return 204;
    }
}
