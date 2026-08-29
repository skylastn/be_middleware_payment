<?php

namespace App\Model\Request\Payout;

use App\Model\Request\BaseRequest;

class CreatePayoutRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string'],
            'gateway' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
            'bank_details' => ['required', 'array'],
            'bank_details.bank_account' => ['required', 'string'],
            'reference' => ['nullable', 'string'],
            'callback_url' => ['nullable', 'string'],
        ];
    }
}
