<?php

namespace App\Repository\Payment;

use App\Enums\OrderStatus;
use App\Model\Entity\Order;
use App\Model\Entity\OrderHistory;
use App\Repository\BaseRepository;

class OrderHistoryRepository extends BaseRepository
{
    /**
     * Log status transition for an order.
     */
    public function log(
        Order $order,
        OrderStatus|string $toStatus,
        string $source = 'SYSTEM',
        ?string $description = null,
        mixed $payload = null,
        OrderStatus|string|null $fromStatus = null
    ): OrderHistory {
        $toStatusValue = OrderStatus::fromName($toStatus)?->value ?? OrderStatus::PENDING->value;
        $fromStatusValue = $fromStatus ? OrderStatus::fromName($fromStatus)?->value : $order->status?->value;

        return OrderHistory::create([
            'order_id' => $order->id,
            'reference' => $order->reference,
            'from_status' => $fromStatusValue,
            'to_status' => $toStatusValue,
            'source' => strtoupper($source),
            'description' => $description,
            'payload' => is_string($payload) ? (json_decode($payload, true) ?: ['raw' => $payload]) : $payload,
            'created_at' => now(),
        ]);
    }

    protected function modelClass(): string
    {
        return OrderHistory::class;
    }
}
