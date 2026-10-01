<?php

namespace App\Model\Response\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentModeType;
use App\Model\Response\Payment\PaymentMethod\PaymentMethodResource;
use App\Model\Response\Payment\PaymentRepository\PaymentRepositoryResource;
use App\Model\Response\Project\ProjectResource;
use App\Model\Response\ResponseResource;
use Illuminate\Http\Request;

class OrderResource extends ResponseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_repository_id' => $this->payment_repository_id,
            'gateway_reference' => $this->gateway_reference,
            'gateway_bill_number' => $this->gateway_bill_number,
            'mode' => $this->mode instanceof PaymentModeType ? $this->mode->value : $this->mode,
            'type' => $this->type,
            'reference' => $this->reference,
            'name' => $this->name,
            'payment_method' => $this->payment_method,
            'amount' => (float) ($this->amount ?? 0),
            'value' => $this->value,
            'status' => $this->status instanceof OrderStatus ? $this->status->value : $this->status,
            'url' => $this->url,
            'return_url' => $this->return_url,
            'notes' => $this->notes,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'request' => $this->request,
            'response' => $this->response,
            'callback' => $this->callback,
            'payment_methods' => new PaymentMethodResource($this->whenLoaded('payment_methods')),
            'payment_repository' => new PaymentRepositoryResource($this->whenLoaded('payment_repository')),
            'project' => $this->whenLoaded('project', fn () => array_diff_key(
                (new ProjectResource($this->project))->resolve($request), ['value' => true],
            )),
            'histories' => OrderHistoryResource::collection($this->whenLoaded('histories')),
            'expired_at' => $this->formatDate($this->expired_at),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
