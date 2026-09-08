<?php

namespace App\Repository\System;

use App\Enums\OrderStatus;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    private function applyOrderFilters(Builder|\Illuminate\Database\Query\Builder $query, ?string $startDate = null, ?string $endDate = null, ?string $paymentRepositoryId = null): void
    {
        if ($startDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $query->where('created_at', '>=', $start);
        }
        if ($endDate) {
            $end = Carbon::parse($endDate)->endOfDay();
            $query->where('created_at', '<=', $end);
        }
        if ($paymentRepositoryId && $paymentRepositoryId !== 'all') {
            $query->where('payment_repository_id', $paymentRepositoryId);
        }
    }

    public function countOrders(?string $startDate = null, ?string $endDate = null, ?string $paymentRepositoryId = null): int
    {
        $query = Order::query();
        $this->applyOrderFilters($query, $startDate, $endDate, $paymentRepositoryId);

        return $query->count();
    }

    public function countProjects(): int
    {
        return Project::count();
    }

    public function countPaymentGateways(): int
    {
        return PaymentGateway::count();
    }

    public function countPaymentRepositories(): int
    {
        return PaymentRepository::count();
    }

    public function countPaymentMethods(): int
    {
        return PaymentMethod::count();
    }

    public function orderStatusCounts(?string $startDate = null, ?string $endDate = null, ?string $paymentRepositoryId = null): Collection
    {
        $query = DB::table('orders');
        $this->applyOrderFilters($query, $startDate, $endDate, $paymentRepositoryId);

        return $query->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn (object $row): array => [($row->status ?: OrderStatus::PENDING->value) => (int) $row->total]);
    }

    public function orderModeCounts(?string $startDate = null, ?string $endDate = null, ?string $paymentRepositoryId = null): Collection
    {
        $query = DB::table('orders');
        $this->applyOrderFilters($query, $startDate, $endDate, $paymentRepositoryId);

        return $query->select('mode', DB::raw('COUNT(*) as total'))
            ->groupBy('mode')
            ->orderBy('mode')
            ->get();
    }

    public function latestOrders(int $limit = 12, ?string $startDate = null, ?string $endDate = null, ?string $paymentRepositoryId = null): Collection
    {
        $query = Order::query();
        $this->applyOrderFilters($query, $startDate, $endDate, $paymentRepositoryId);

        return $query->latest()->limit($limit)->get();
    }

    public function latestProjects(int $limit = 8): Collection
    {
        return Project::query()->latest()->limit($limit)->get();
    }

    public function latestPaymentRepositories(int $limit = 8): Collection
    {
        return PaymentRepository::query()
            ->with('payment_gateway')
            ->latest()
            ->limit($limit)
            ->get();
    }
}

