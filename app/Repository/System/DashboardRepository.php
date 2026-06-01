<?php

namespace App\Repository\System;

use App\Model\Entity\Order;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\PaymentRepository;
use App\Model\Entity\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function countOrders(): int
    {
        return Order::count();
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

    public function orderStatusCounts(): Collection
    {
        return DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn (object $row): array => [($row->status ?: 'UNKNOWN') => (int) $row->total]);
    }

    public function orderModeCounts(): Collection
    {
        return DB::table('orders')
            ->select('mode', DB::raw('COUNT(*) as total'))
            ->groupBy('mode')
            ->orderBy('mode')
            ->get();
    }

    public function latestOrders(int $limit = 12): Collection
    {
        return Order::query()->latest()->limit($limit)->get();
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
