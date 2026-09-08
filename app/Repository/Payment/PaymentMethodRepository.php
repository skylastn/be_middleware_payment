<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentMethod;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentMethod::class;
    }

    public function filtered(array|string|null $categoriesKey = null, ?string $from = null): Collection
    {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::with('category')
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->when($from, fn ($query) => $query->where('from', $from))
            ->get();
    }

    public function detail(?string $key, ?string $from = null): ?PaymentMethod
    {
        return PaymentMethod::with('category')
            ->when($key, fn ($query) => $query->where('key', $key))
            ->when($from, fn ($query) => $query->where('from', $from))
            ->first();
    }

    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $from = null,
        array|string|null $categoriesKey = null
    ): LengthAwarePaginator {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::with('category')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('from', 'like', "%{$search}%")
                        ->orWhere('bankCode', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($catQuery) use ($search) {
                            $catQuery->where('key', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                });
            })
            ->when($from, fn ($query) => $query->where('from', $from))
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->latest()
            ->paginate($perPage);
    }
}
