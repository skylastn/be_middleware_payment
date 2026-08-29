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

    public function latestPaginated(int $perPage = 10, ?string $search = null, ?string $slug = null): LengthAwarePaginator
    {
        return Project::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('callback', 'like', "%{$search}%");
                });
            })
            ->when($slug && $slug !== 'all', fn ($query) => $query->where('slug', $slug))
            ->latest()
            ->paginate($perPage);
    }

    protected function modelClass(): string
    {
        return Project::class;
    }
}
