<?php

namespace App\Repository\System;

use App\Model\Entity\Project;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository extends BaseRepository
{
    public function findByToken(?string $token): ?Project
    {
        return Project::where('value', $token)->first();
    }

    public function findByType(?string $type): ?Project
    {
        return Project::where('type', $type)->first();
    }

    public function latestPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Project::latest()->paginate($perPage);
    }

    protected function modelClass(): string
    {
        return Project::class;
    }
}
