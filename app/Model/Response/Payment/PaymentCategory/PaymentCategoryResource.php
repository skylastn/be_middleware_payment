<?php

namespace App\Model\Response\Payment\PaymentCategory;

use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PaymentCategoryResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'title' => $this->title,
            'detail' => $this->detail,
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
