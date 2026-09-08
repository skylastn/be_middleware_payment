<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentCategory;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentCategoryRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentCategory::class;
    }
    
    public function latestPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return PaymentCategory::query()
            ->when($search, function ($query, $search) {
                $query->where('key', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage);
    }
}
