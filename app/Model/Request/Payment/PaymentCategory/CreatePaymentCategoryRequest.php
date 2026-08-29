<?php

namespace App\Model\Request\Payment\PaymentCategory;

use App\Model\Request\BaseRequest;

class CreatePaymentCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string'],
        ];
    }
}
