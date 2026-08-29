<?php

namespace App\Repository;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    public function all(array $with = []): Collection
    {
        return $this->query()->with($with)->get();
    }

    public function paginate(int $perPage = 10, array $with = []): LengthAwarePaginator
    {
        return $this->query()->with($with)->paginate($perPage);
    }

    public function find(int|string $id, array $with = []): ?Model
    {
        return $this->query()->with($with)->find($id);
    }

    public function create(array $data): Model
    {
        return $this->modelClass()::create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    protected function query(): Builder
    {
        return $this->modelClass()::query();
    }

    abstract protected function modelClass(): string;
}
