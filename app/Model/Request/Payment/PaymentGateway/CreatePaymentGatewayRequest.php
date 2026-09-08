<?php

namespace App\Model\Request\Payment\PaymentGateway;

use App\Model\Request\BaseRequest;

class CreatePaymentGatewayRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
