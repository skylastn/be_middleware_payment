<?php

namespace App\Model\Request\Payment\PaymentMethod;

use App\Model\Request\BaseRequest;

class CreatePaymentMethodRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'payment_gateway_id' => ['required', 'string', 'exists:payment_gateways,id'],
            'bankCode' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
