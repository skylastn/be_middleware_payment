<?php

namespace App\Model\Response\Payment\PaymentGateway;

use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PaymentGatewayResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
