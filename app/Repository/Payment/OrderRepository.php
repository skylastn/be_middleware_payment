<?php

namespace App\Repository\Payment;

use App\Model\Entity\Order;
use App\Repository\BaseRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $mode = null,
        ?string $status = null,
        ?string $type = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentRepositoryId = null
    ): LengthAwarePaginator {
        return Order::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($mode && $mode !== 'all', fn ($query) => $query->where('mode', $mode))
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($type && $type !== 'all', fn ($query) => $query->where('type', $type))
            ->when($paymentRepositoryId && $paymentRepositoryId !== 'all', fn ($query) => $query->where('payment_repository_id', $paymentRepositoryId))
            ->when($startDate, fn ($query) => $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay()))
            ->when($endDate, fn ($query) => $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay()))
            ->latest()
            ->paginate($perPage);
    }

    public function latestByReference(string $reference, ?string $projectType = null): ?Order
    {
        return Order::where('reference', $reference)
            ->when($projectType, fn ($query) => $query->where('type', $projectType))
            ->latest()
            ->first();
    }

    public function findById(int|string $id): ?Order
    {
        return $this->find($id);
    }

    protected function modelClass(): string
    {
        return Order::class;
    }
}
