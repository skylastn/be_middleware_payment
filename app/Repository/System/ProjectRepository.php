<?php

namespace App\Repository\System;

use App\Model\Entity\Project;
use App\Repository\BaseRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    public function firstOrCreate(array $attributes, array $values = []): Project
    {
        return Project::firstOrCreate($attributes, $values);
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

    public function ensureLogTableExists(int|string $projectId): string
    {
        $tableName = 'z__log__' . $projectId;
        if (! Schema::hasTable($tableName)) {
            $legacyTable = 'log__' . $projectId;
            if (Schema::hasTable($legacyTable)) {
                Schema::rename($legacyTable, $tableName);
            } else {
                Schema::create($tableName, function (Blueprint $table): void {
                    $table->increments('id');
                    $table->string('key');
                    $table->text('value');
                    $table->text('ip');
                    $table->timestamps();
                });
            }
        }

        return $tableName;
    }

    public function getProjectLogs(
        int|string $projectId,
        int $perPage = 20,
        ?string $search = null,
        ?string $key = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): LengthAwarePaginator {
        $tableName = $this->ensureLogTableExists($projectId);

        return DB::table($tableName)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            })
            ->when($key && $key !== 'all', fn ($query) => $query->where('key', $key))
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return array<int, string>
     */
    public function getDistinctLogKeys(int|string $projectId): array
    {
        $tableName = $this->ensureLogTableExists($projectId);

        return DB::table($tableName)
            ->distinct()
            ->pluck('key')
            ->filter()
            ->values()
            ->all();
    }

    public function clearProjectLogs(int|string $projectId): bool
    {
        $tableName = $this->ensureLogTableExists($projectId);
        DB::table($tableName)->truncate();

        return true;
    }

    protected function modelClass(): string
    {
        return Project::class;
    }
}
