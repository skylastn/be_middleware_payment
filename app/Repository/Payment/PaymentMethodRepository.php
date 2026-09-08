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

    public function filtered(
        array|string|null $categoriesKey = null,
        ?string $paymentGatewayId = null,
        ?bool $isActive = null
    ): Collection {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::with(['category', 'payment_gateway'])
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->when($paymentGatewayId, function ($query, $paymentGatewayId) {
                $query->where(function ($q) use ($paymentGatewayId) {
                    $q->where('payment_gateway_id', $paymentGatewayId)
                        ->orWhereHas('payment_gateway', fn ($gw) => $gw->where('key', $paymentGatewayId));
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->get();
    }

    public function detail(?string $key, ?string $paymentGatewayId = null, ?bool $isActive = null): ?PaymentMethod
    {
        return PaymentMethod::with(['category', 'payment_gateway'])
            ->when($key, fn ($query) => $query->where('key', $key))
            ->when($paymentGatewayId, function ($query, $paymentGatewayId) {
                $query->where(function ($q) use ($paymentGatewayId) {
                    $q->where('payment_gateway_id', $paymentGatewayId)
                        ->orWhereHas('payment_gateway', fn ($gw) => $gw->where('key', $paymentGatewayId));
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->first();
    }

    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $paymentGatewayId = null,
        array|string|null $categoriesKey = null,
        ?bool $isActive = null
    ): LengthAwarePaginator {
        $categoriesKey = is_string($categoriesKey) ? [$categoriesKey] : $categoriesKey;

        return PaymentMethod::with(['category', 'payment_gateway'])
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
            ->when($paymentGatewayId, function ($query, $paymentGatewayId) {
                $query->where(function ($q) use ($paymentGatewayId) {
                    $q->where('payment_gateway_id', $paymentGatewayId)
                        ->orWhereHas('payment_gateway', fn ($gw) => $gw->where('key', $paymentGatewayId));
                });
            })
            ->when($categoriesKey, function ($query) use ($categoriesKey) {
                $query->where(function ($q) use ($categoriesKey) {
                    $q->whereIn('key', $categoriesKey)
                        ->orWhereHas('category', fn ($catQuery) => $catQuery->whereIn('key', $categoriesKey));
                });
            })
            ->when($isActive !== null, fn ($query) => $query->where('is_active', $isActive))
            ->latest()
            ->paginate($perPage);
    }
}
