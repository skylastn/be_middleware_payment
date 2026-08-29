<?php

namespace App\Repository\System;

use App\Model\Entity\Setting;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SettingRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return Setting::class;
    }

    public function latestPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return Setting::query()
            ->when($search, function ($query, $search) {
                $query->where('key', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage);
    }
}
