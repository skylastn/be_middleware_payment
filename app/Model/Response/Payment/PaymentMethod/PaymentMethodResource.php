<?php

namespace App\Model\Response\Payment\PaymentMethod;

use App\Model\Response\Payment\PaymentCategory\PaymentCategoryResource;
use App\Model\Response\Payment\PaymentGateway\PaymentGatewayResource;
use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class PaymentMethodResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = null;
        if (! empty($this->image)) {
            if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
                $imageUrl = $this->image;
            } else {
                $relativePath = ltrim(preg_replace('#^storage/#', '', $this->image), '/');
                $baseUrl = rtrim(config('app.url', env('APP_URL', 'http://localhost:8000')), '/');
                $imageUrl = $baseUrl . '/storage/' . $relativePath;
            }
        }

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'type' => $this->category?->key ?? null,
            'payment_gateway_id' => $this->payment_gateway_id,
            'payment_gateway' => $this->payment_gateway ? new PaymentGatewayResource($this->payment_gateway) : null,
            'gateway' => $this->payment_gateway ? new PaymentGatewayResource($this->payment_gateway) : null,
            'bankCode' => $this->bankCode,
            'image' => $this->image,
            'image_url' => $imageUrl,
            'is_active' => (bool) ($this->is_active ?? true),
            'category' => $this->category ? new PaymentCategoryResource($this->category) : null,
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
