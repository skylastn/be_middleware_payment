<?php

namespace App\Model\Request\Payment;

use App\Model\Request\BaseRequest;

class CreatePaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string'],
            'paymentMethod' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
