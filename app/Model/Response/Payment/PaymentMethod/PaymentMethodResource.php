<?php

namespace App\Model\Response\Payment\PaymentMethod;

use App\Model\Response\Payment\PaymentCategory\PaymentCategoryResource;
use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PaymentMethodResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'type' => $this->category?->key ?? null,
            'from' => $this->from,
            'bankCode' => $this->bankCode,
            'value' => $this->value,
            'category' => $this->category ? new PaymentCategoryResource($this->category) : null,
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
