<?php

namespace App\Model\Request\Order;

use App\Model\Request\BaseRequest;

class OrderListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'mode' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'payment_repository_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
