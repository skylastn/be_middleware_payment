<?php

namespace App\Model\Response\Payment\PaymentRepository;

use App\Enums\PaymentModeType;
use App\Model\Response\Payment\PaymentGateway\PaymentGatewayResource;
use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PaymentRepositoryResource extends ResponseResource
{
    public function __construct($resource, private bool $includeCredentials = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_gateway_id' => $this->payment_gateway_id,
            'key' => $this->key,
            'mode' => $this->mode instanceof PaymentModeType ? $this->mode->value : $this->mode,
            'value' => $this->when($this->includeCredentials, fn () => $this->resource->getValue()),
            'payment_gateway' => new PaymentGatewayResource($this->whenLoaded('payment_gateway')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
