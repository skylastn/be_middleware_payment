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
            'from' => ['nullable', 'string', 'max:50'],
            'bankCode' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
        ];
    }
}
