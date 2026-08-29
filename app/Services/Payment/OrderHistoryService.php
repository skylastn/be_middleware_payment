<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Model\Entity\Order;
use App\Model\Entity\OrderHistory;
use App\Repository\Payment\OrderHistoryRepository;
use Illuminate\Database\Eloquent\Collection;

class OrderHistoryService
{
    private OrderHistoryRepository $repository;

    public function __construct(?OrderHistoryRepository $repository = null)
    {
        $this->repository = $repository ?? new OrderHistoryRepository();
    }

    /**
     * Record a status transition history for an order.
     */
    public function log(
        Order $order,
        OrderStatus|string $toStatus,
        string $source = 'SYSTEM',
        ?string $description = null,
        mixed $payload = null,
        OrderStatus|string|null $fromStatus = null
    ): OrderHistory {
        return $this->repository->log($order, $toStatus, $source, $description, $payload, $fromStatus);
    }

    /**
     * Get all history audit logs for a specific order.
     *
     * @return Collection<int, OrderHistory>
     */
    public function getByOrderId(string $orderId): Collection
    {
        return OrderHistory::query()
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
