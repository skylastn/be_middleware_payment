<?php

namespace App\Services\System;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Enums\ProjectSlug;
use App\Repository\System\DashboardRepository;
use Illuminate\Http\Request;

class DashboardService
{
    public function __construct(private ?DashboardRepository $dashboard = null)
    {
        $this->dashboard ??= new DashboardRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function monitoringData(?Request $request = null): array
    {
        $startDate = $request?->query('start_date') ?: $request?->query('startDate') ?: now()->startOfMonth()->toDateString();
        $endDate = $request?->query('end_date') ?: $request?->query('endDate') ?: now()->endOfMonth()->toDateString();
        $paymentRepositoryId = $request?->query('payment_repository_id') ?: $request?->query('paymentRepositoryId');

        $statusCounts = $this->dashboard->orderStatusCounts($startDate, $endDate, $paymentRepositoryId);
        $modeCounts = $this->dashboard->orderModeCounts($startDate, $endDate, $paymentRepositoryId);

        $successTotal = (int) ($statusCounts[OrderStatus::SUCCESS->value] ?? 0);
        $pendingTotal = (int) ($statusCounts[OrderStatus::PENDING->value] ?? 0);
        $failedTotal = $this->sumStatuses($statusCounts, fn (OrderStatus $status): bool => $status->isFailed());
        $totalStatuses = max((int) $statusCounts->sum(), 1);

        return [
            'summary' => [
                'orders' => $this->dashboard->countOrders($startDate, $endDate, $paymentRepositoryId),
                'paidOrders' => $successTotal,
                'pendingOrders' => $pendingTotal,
                'failedOrders' => $failedTotal,
                'projects' => $this->dashboard->countProjects(),
                'paymentGateways' => $this->dashboard->countPaymentGateways(),
                'paymentRepositories' => $this->dashboard->countPaymentRepositories(),
                'paymentMethods' => $this->dashboard->countPaymentMethods(),
            ],
            'statusCounts' => $statusCounts->all(),
            'statusMix' => [
                'success' => $successTotal,
                'pending' => $pendingTotal,
                'failedExpired' => $failedTotal,
                'successDeg' => round(($successTotal / $totalStatuses) * 360, 2),
                'pendingDeg' => round(($pendingTotal / $totalStatuses) * 360, 2),
            ],
            'modeCounts' => $modeCounts
                ->map(fn (object $mode): array => [
                    'mode' => $mode->mode ?: 'UNKNOWN',
                    'total' => (int) $mode->total,
                ])
                ->values()
                ->all(),
            'recentOrders' => $this->dashboard->latestOrders(12, $startDate, $endDate, $paymentRepositoryId)
                ->map(fn ($order): array => [
                    'reference' => (string) $order->reference,
                    'type' => (string) $order->type,
                    'paymentMethod' => $order->payment_method ?: '-',
                    'status' => $this->statusValue($order->status),
                    'statusClass' => $this->statusClass($order->status),
                    'mode' => $this->modeValue($order->mode),
                    'createdAt' => $order->created_at?->format('Y-m-d H:i'),
                ])
                ->values()
                ->all(),
            'projects' => $this->dashboard->latestProjects()
                ->map(fn ($project): array => [
                    'name' => (string) $project->name,
                    'type' => (string) $project->type,
                    'slug' => $this->slugValue($project->slug),
                    'callback' => (string) $project->callback,
                ])
                ->values()
                ->all(),
            'repositories' => $this->dashboard->latestPaymentRepositories(100)
                ->map(fn ($repository): array => [
                    'id' => (string) $repository->id,
                    'gateway' => $repository->payment_gateway?->name ?? 'Unknown gateway',
                    'mode' => $this->modeValue($repository->mode),
                ])
                ->values()
                ->all(),
            'filters' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'paymentRepositoryId' => $paymentRepositoryId,
            ],
            'updatedAt' => now()->format('Y-m-d H:i'),
        ];
    }

    private function sumStatuses(mixed $statusCounts, callable $filter): int
    {
        $total = 0;

        foreach ($statusCounts as $status => $count) {
            $orderStatus = OrderStatus::fromName((string) $status);
            if ($orderStatus && $filter($orderStatus)) {
                $total += (int) $count;
            }
        }

        return $total;
    }

    private function statusValue(mixed $value): string
    {
        return $this->orderStatus($value)?->value ?? OrderStatus::PENDING->value;
    }

    private function statusClass(mixed $value): string
    {
        $status = $this->orderStatus($value);

        if ($status?->isSuccess()) {
            return 'success';
        }

        return $status?->isFailed() ? 'danger' : 'warning';
    }

    private function orderStatus(mixed $value): ?OrderStatus
    {
        return $value instanceof OrderStatus
            ? $value
            : OrderStatus::fromName(is_string($value) ? $value : null);
    }

    private function modeValue(mixed $value): string
    {
        return $value instanceof PaymentModeType
            ? $value->value
            : (string) ($value ?: 'UNKNOWN');
    }

    private function slugValue(mixed $value): string
    {
        return $value instanceof ProjectSlug
            ? $value->value
            : (string) $value;
    }
}
