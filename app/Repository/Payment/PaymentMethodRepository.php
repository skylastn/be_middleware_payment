<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentMethod;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class PaymentMethodRepository extends BaseRepository
{
    public function filtered(array|string|null $categoriesKey = null, ?string $from = null): Collection
    {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::when($categoriesKey, fn ($query) => $query->whereIn('key', $categoriesKey))
            ->when($from, fn ($query) => $query->where('from', $from))
            ->get();
    }

    public function detail(?string $value, ?string $from): ?PaymentMethod
    {
        return PaymentMethod::when($value, fn ($query) => $query->where('value', $value))
            ->when($from, fn ($query) => $query->where('from', $from))
            ->first();
    }

    protected function modelClass(): string
    {
        return PaymentMethod::class;
    }
}
