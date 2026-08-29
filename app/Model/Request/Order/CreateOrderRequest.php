<?php

namespace App\Model\Request\Order;

use App\Model\Request\BaseRequest;

class CreateOrderRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'merchantOrderId' => ['nullable', 'string'],
            'paymentAmount' => ['nullable', 'numeric'],
            'paymentMethod' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
            'currency' => ['nullable', 'string'],
            'productDetails' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'returnUrl' => ['nullable', 'string'],
            'callbackUrl' => ['nullable', 'string'],
        ];
    }
}
