<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentMethod;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentMethod::class;
    }
    
    public function filtered(array|string|null $categoriesKey = null, ?string $from = null): Collection
    {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::when($categoriesKey, fn($query) => $query->whereIn('key', $categoriesKey))
            ->when($from, fn($query) => $query->where('from', $from))
            ->get();
    }

    public function detail(?string $value, ?string $from): ?PaymentMethod
    {
        return PaymentMethod::when($value, fn($query) => $query->where('value', $value))
            ->when($from, fn($query) => $query->where('from', $from))
            ->first();
    }

    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $from = null,
        array|string|null $categoriesKey = null
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('bankCode', 'like', "%{$search}%")
                        ->orWhere('value', 'like', "%{$search}%");
                });
            })
            ->when($from, fn($query) => $query->where('from', $from))
            ->when($categoriesKey, fn($query) => $query->whereIn('key', $categoriesKey))
            ->latest()
            ->paginate($perPage);
    }
}
