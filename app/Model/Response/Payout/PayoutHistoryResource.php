<?php

namespace App\Model\Response\Payout;

use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PayoutHistoryResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_id' => $this->payout_id,
            'action' => $this->action,
            'status' => $this->status,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'performed_at' => $this->formatDate($this->performed_at),
        ];
    }
}
