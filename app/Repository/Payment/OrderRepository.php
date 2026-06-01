<?php

namespace App\Repository\Payment;

use App\Model\Entity\Order;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    public function latestPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Order::latest()->paginate($perPage);
    }

    public function latestByType(string $type, int $perPage = 15): LengthAwarePaginator
    {
        return Order::where('type', $type)->latest()->paginate($perPage);
    }

    public function latestByReference(string $reference, ?string $projectType = null): ?Order
    {
        return Order::where('reference', $reference)
            ->when($projectType, fn ($query) => $query->where('type', $projectType))
            ->latest()
            ->first();
    }

    protected function modelClass(): string
    {
        return Order::class;
    }
}
