<?php

namespace App\Services\System;

use App\Enums\ProjectSlug;
use App\Repository\System\ProjectRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Model\Entity\Project;
use Exception;
use Illuminate\Support\Str;

class ProjectService
{
    public function __construct(private ?ProjectRepository $projects = null)
    {
        $this->projects ??= new ProjectRepository();
    }

    public function checkKey(): Project
    {
        $project = $this->projects->findByToken(request()->header('Token'));
        if (!isset($project)) {
            throw new Exception('Unauthorized');
        }
        return $project;
    }

    public function getListProject(Request $request): LengthAwarePaginator
    {
        return $this->projects->latestPaginated((int) ($request->perPage ?? 15));
    }

    public function getProjectById(int|string $id): ?Project
    {
        return $this->projects->find($id);
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
        /** @var Project $project */
        $project = $this->projects->create($data);

        return $project;
    }

    public function update(Request $request, int|string $id): Project
    {
        $project = Project::findOrFailCustom($id);
        $project->setName($request->name);
        $project->setType($request->type);
        $project->setSlug(ProjectSlug::fromName($request->slug));
        $project->setCallback($request->callback);
        return $this->projects->update($project, [
            'name' => $request->name,
            'type' => $request->type,
            'slug' => ProjectSlug::fromName($request->slug),
            'callback' => $request->callback,
        ]);
    }

    public function delete(int|string $id): Project
    {
        $project = Project::findOrFailCustom($id);
        $this->projects->delete($project);

        return $project;
    }
}
