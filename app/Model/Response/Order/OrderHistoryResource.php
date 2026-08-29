<?php

namespace App\Model\Response\Order;

use App\Enums\OrderStatus;
use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class OrderHistoryResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'reference' => $this->reference,
            'from_status' => $this->from_status instanceof OrderStatus ? $this->from_status->value : $this->from_status,
            'to_status' => $this->to_status instanceof OrderStatus ? $this->to_status->value : $this->to_status,
            'source' => $this->source,
            'description' => $this->description,
            'payload' => $this->payload,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
