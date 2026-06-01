<?php

namespace App\Model\Response;

use Illuminate\Http\Request;

class OrderResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'type' => $this->type,
            'reference' => $this->reference,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'url' => $this->url,
            'notes' => $this->notes,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
