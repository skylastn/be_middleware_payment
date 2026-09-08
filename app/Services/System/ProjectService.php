<?php

namespace App\Services\System;

use App\Enums\ProjectSlug;
use App\Repository\System\ProjectRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use App\Model\Entity\Project;
use App\Model\Response\Project\ProjectLogResource;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private ?ProjectRepository $projects = null)
    {
        $this->projects ??= new ProjectRepository();
    }

    public function checkKey(): Project
    {
        $token = request()->header('Token');
        if (!$token) {
            throw new Exception('Unauthorized');
        }

        $project = $this->projects->findByToken($token);
        if (!isset($project)) {
            throw new Exception('Unauthorized');
        }
        return $project;
    }

    public function getListProject(Request $request): LengthAwarePaginator
    {
        $search = $request->query('search');
        $slug = $request->query('slug');
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));

        return $this->projects->latestPaginated($perPage, $search, $slug);
    }

    public function getProjectById(int|string $id): ?Project
    {
        return $this->projects->find($id);
    }

    public function getByType(string $type): ?Project
    {
        return $this->projects->findByType($type);
    }

    public function firstOrCreate(array $attributes, array $values = []): Project
    {
        return $this->projects->firstOrCreate($attributes, $values);
    }

    public function create(Request $request): Project
    {
        $payload = $this->payload($request);

        $data['name']       = $payload['name'];
        $data['type']       = $payload['type'];
        $data['slug']       = ProjectSlug::fromName($payload['slug']);
        $data['key']        = Str::random(10);
        $data['secure']     = Str::random(20);
        $data['callback']   = $payload['callback'];
        $data['value']      = Str::random(60);
        /** @var Project $project */
        $project = $this->projects->create($data);

        return $project;
    }

    public function createWithLog(Request $request): Project
    {
        $project = $this->create($request);
        $this->createLogTable($project);

        return $project;
    }

    /**
     * @return array{checked: int, created: int, existing: int, tables: array<int, string>}
     */
    public function syncMissingLogTables(): array
    {
        $checked = 0;
        $createdTables = [];

        Project::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($projects) use (&$checked, &$createdTables): void {
                foreach ($projects as $project) {
                    $checked++;
                    $tableName = $this->logTableName($project);

                    if ($this->createLogTable($project)) {
                        $createdTables[] = $tableName;
                    }
                }
            });

        return [
            'checked' => $checked,
            'created' => count($createdTables),
            'existing' => $checked - count($createdTables),
            'tables' => $createdTables,
        ];
    }

    public function update(Request $request, int|string $id): Project
    {
        $payload = $this->payload($request);
        $project = Project::findOrFailCustom($id);
        $project->setName($payload['name']);
        $project->setType($payload['type']);
        $project->setSlug(ProjectSlug::fromName($payload['slug']));
        $project->setCallback($payload['callback']);
        return $this->projects->update($project, [
            'name' => $payload['name'],
            'type' => $payload['type'],
            'slug' => ProjectSlug::fromName($payload['slug']),
            'callback' => $payload['callback'],
        ]);
    }

    public function getProjectLogs(int|string $id, Request $request): LengthAwarePaginator
    {
        $project = Project::findOrFailCustom($id);
        $search = $request->query('search');
        $key = $request->query('key');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 20)));

        $paginator = $this->projects->getProjectLogs($project->id, $perPage, $search, $key, $dateFrom, $dateTo);

        return $paginator->through(fn ($item) => (new ProjectLogResource($item))->toArray(request()));
    }

    public function getProjectLogKeys(int|string $id): array
    {
        $project = Project::findOrFailCustom($id);

        return $this->projects->getDistinctLogKeys($project->id);
    }

    public function clearProjectLogs(int|string $id): bool
    {
        $project = Project::findOrFailCustom($id);

        return $this->projects->clearProjectLogs($project->id);
    }

    public function delete(int|string $id): Project
    {
        $project = Project::findOrFailCustom($id);
        $this->projects->delete($project);

        return $project;
    }

    private function createLogTable(Project $project): bool
    {
        $tableName = $this->logTableName($project);
        if (Schema::hasTable($tableName)) {
            return false;
        }

        Schema::create($tableName, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key');
            $table->text('value');
            $table->text('ip');
            $table->timestamps();
        });

        return true;
    }

    private function logTableName(Project $project): string
    {
        return 'z__log__'.$project->id;
    }

    /**
     * @return array{name: string, type: string, slug: string, callback: string}
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'type' => ['required', 'string'],
            'slug' => ['required', 'string'],
            'callback' => ['required', 'string'],
        ]);

        if (! ProjectSlug::tryFrom($data['slug'])) {
            throw ValidationException::withMessages([
                'slug' => 'Slug must be one of: '.implode(', ', array_map(fn (ProjectSlug $slug): string => $slug->value, ProjectSlug::cases())).'.',
            ]);
        }

        return $data;
    }
}
