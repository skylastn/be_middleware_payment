<?php

namespace App\Services\System;

use App\Enums\OrderStatus;
use App\Repository\System\DashboardRepository;

class DashboardService
{
    public function __construct(private ?DashboardRepository $dashboard = null)
    {
        $this->dashboard ??= new DashboardRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function monitoringData(): array
    {
        $statusCounts = $this->dashboard->orderStatusCounts();

        return [
            'summary' => [
                'orders' => $this->dashboard->countOrders(),
                'paidOrders' => $this->sumStatuses($statusCounts, fn (OrderStatus $status): bool => $status->isSuccess()),
                'pendingOrders' => (int) ($statusCounts[OrderStatus::PENDING->value] ?? 0),
                'failedOrders' => $this->sumStatuses($statusCounts, fn (OrderStatus $status): bool => $status->isFailed()),
                'projects' => $this->dashboard->countProjects(),
                'paymentGateways' => $this->dashboard->countPaymentGateways(),
                'paymentRepositories' => $this->dashboard->countPaymentRepositories(),
                'paymentMethods' => $this->dashboard->countPaymentMethods(),
            ],
            'statusCounts' => $statusCounts,
            'modeCounts' => $this->dashboard->orderModeCounts(),
            'recentOrders' => $this->dashboard->latestOrders(),
            'projects' => $this->dashboard->latestProjects(),
            'repositories' => $this->dashboard->latestPaymentRepositories(),
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
}
