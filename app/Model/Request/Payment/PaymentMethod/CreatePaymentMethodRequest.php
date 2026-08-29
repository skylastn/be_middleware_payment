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
            'type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'string', 'max:50'],
            'bankCode' => ['nullable', 'string', 'max:50'],
            'value' => ['nullable', 'string', 'max:100'],
        ];
    }
}
