<?php

namespace App\Services;

use App\Enums\ProjectSlug;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Models\Project;
use Exception;
use Illuminate\Support\Str;

class ProjectService
{
    public function checkKey(): Project
    {
        $result['status'] = true;
        $where['value'] = request()->header('Token');
        $project = Project::where($where)->first();
        if (!isset($project)) {
            throw new Exception('Unauthorized');
        }
        return $project;
    }

    public function getListProject(Request $request): LengthAwarePaginator
    {
        return Project::latest()->paginate($request->perPage);
    }

    public function getProjectById($id): Project
    {
        return Project::find($id);
    }

    public function create(Request $request): Project
    {
        $data['name']       = $request->name;
        $data['type']       = $request->type;
        $data['slug']       = ProjectSlug::fromName($request->slug);
        $data['key']        = Str::random(10);
        $data['secure']     = Str::random(20);
        $data['callback']   = $request->callback;
        $data['value']      = Str::random(60);
        $insert = Project::create($data);
        return $insert;
    }

    public function update(Request $request, $id): Project
    {
        $project = Project::findOrFailCustom($id);
        $project->setName($request->name);
        $project->setType($request->type);
        $project->setSlug(ProjectSlug::fromName($request->slug));
        $project->setCallback($request->callback);
        $project->save();
        return $project;
    }

    public function delete($id): Project
    {
        $project = Project::findOrFailCustom($id);
        $project->delete();
        return $project;
    }
}
