<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentMethod;
use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PaymentMethodRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentMethod::class;
    }

    private function applyGatewayFilter(Builder $query, ?string $paymentGatewayId, ?string $paymentGatewayKey = null): Builder
    {
        if ($paymentGatewayId) {
            if (Str::isUuid($paymentGatewayId)) {
                $query->where('payment_gateway_id', $paymentGatewayId);
            } else {
                $query->where(function ($q) use ($paymentGatewayId) {
                    $q->where('payment_gateway_id', $paymentGatewayId)
                        ->orWhereHas('payment_gateway', fn ($gw) => $gw->where('key', $paymentGatewayId));
                });
            }
        }

        if ($paymentGatewayKey) {
            $query->whereHas('payment_gateway', fn ($gw) => $gw->where('key', $paymentGatewayKey));
        }

        return $query;
    }

    public function filtered(
        array|string|null $categoriesKey = null,
        ?string $paymentGatewayId = null,
        ?bool $isActive = null,
        ?string $paymentGatewayKey = null
    ): Collection {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        $query = PaymentMethod::with(['category', 'payment_gateway'])
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive));

        return $this->applyGatewayFilter($query, $paymentGatewayId, $paymentGatewayKey)->get();
    }

    public function detail(
        ?string $key,
        ?string $paymentGatewayId = null,
        ?bool $isActive = null,
        ?string $paymentGatewayKey = null
    ): ?PaymentMethod {
        $query = PaymentMethod::with(['category', 'payment_gateway'])
            ->when($key, fn ($query) => $query->where('key', $key))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive));

        return $this->applyGatewayFilter($query, $paymentGatewayId, $paymentGatewayKey)->first();
    }

    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $paymentGatewayId = null,
        array|string|null $categoriesKey = null,
        ?bool $isActive = null,
        ?string $paymentGatewayKey = null
    ): LengthAwarePaginator {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        $query = PaymentMethod::with(['category', 'payment_gateway'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('bankCode', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($catQuery) use ($search) {
                            $catQuery->where('key', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('payment_gateway', function ($gwQuery) use ($search) {
                            $gwQuery->where('key', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive));

        return $this->applyGatewayFilter($query, $paymentGatewayId, $paymentGatewayKey)
            ->latest()
            ->paginate($perPage);
    }
}
