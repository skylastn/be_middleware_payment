<?php

namespace App\Model\Request\Payment\PaymentRepository;

use App\Enums\PaymentModeType;
use App\Model\Request\BaseRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRepositoryRequest extends BaseRequest
{
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'payment_gateway_id' => [$isUpdate ? 'sometimes' : 'required', 'string'],
            'key' => ['nullable', 'string', 'max:100'],
            'mode' => [$isUpdate ? 'sometimes' : 'required', 'string', Rule::in(array_map(fn (PaymentModeType $m) => $m->value, PaymentModeType::cases()))],
            'value' => ['required'],
        ];
    }
}
