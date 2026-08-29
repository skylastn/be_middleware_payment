<?php

namespace App\Model\Response\Payout;

use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PayoutResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'reference' => $this->reference,
            'caller_reference' => $this->caller_reference,
            'gateway' => $this->gateway?->value ?? $this->gateway,
            'gateway_payout_id' => $this->gateway_payout_id,
            'mode' => $this->mode?->value ?? $this->mode,
            'status' => $this->status?->value ?? $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'recipient_name' => $this->recipient_name,
            'bank_code' => $this->bank_code,
            'bank_account' => $this->bank_account,
            'callback_url' => $this->callback_url,
            'histories' => PayoutHistoryResource::collection($this->whenLoaded('histories')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
