<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentGateway;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentGatewayRepository extends BaseRepository
{

    protected function modelClass(): string
    {
        return PaymentGateway::class;
    }
    public function findByKey(string $key): ?PaymentGateway
    {
        return PaymentGateway::where('key', $key)->first();
    }

    public function latestPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return PaymentGateway::query()
            ->when($search, function ($query, $search) {
                $query->where('key', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage);
    }
}
