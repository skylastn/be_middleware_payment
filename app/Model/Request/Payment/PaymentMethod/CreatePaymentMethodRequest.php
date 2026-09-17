<?php

namespace App\Model\Request\Payment\PaymentMethod;

use App\Model\Request\BaseRequest;

class CreatePaymentMethodRequest extends BaseRequest
{
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $requiredRule = $isUpdate ? 'sometimes' : 'required';

        return [
            'key' => [$requiredRule, 'string', 'max:50'],
            'name' => [$requiredRule, 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'payment_gateway_id' => [$requiredRule, 'string', 'exists:payment_gateways,id'],
            'bankCode' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
