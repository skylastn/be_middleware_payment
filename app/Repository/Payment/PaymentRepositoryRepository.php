<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentRepository;
use App\Repository\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentRepositoryRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return PaymentRepository::class;
    }
    
    public function findByGatewayAndMode(string $paymentGatewayId, string $mode): ?PaymentRepository
    {
        return PaymentRepository::where('payment_gateway_id', $paymentGatewayId)
            ->where('mode', $mode)
            ->first();
    }

    public function findByGatewayAndClientKey(string $paymentGatewayId, string $clientKey): ?PaymentRepository
    {
        return PaymentRepository::where('payment_gateway_id', $paymentGatewayId)
            ->where(function ($query) use ($clientKey) {
                $query->where('value->api_client', $clientKey)
                    ->orWhere('value->api_key_paprika', $clientKey)
                    ->orWhere('value', 'like', '%"api_client":"' . $clientKey . '"%')
                    ->orWhere('value', 'like', '%"api_client": "' . $clientKey . '"%')
                    ->orWhere('value', 'like', '%"api_key_paprika":"' . $clientKey . '"%')
                    ->orWhere('value', 'like', '%"api_key_paprika": "' . $clientKey . '"%');
            })
            ->first();
    }

    public function findByGatewayAndSnapClientId(string $paymentGatewayId, string $clientId): ?PaymentRepository
    {
        return PaymentRepository::where('payment_gateway_id', $paymentGatewayId)
            ->where(function ($query) use ($clientId) {
                $query->where('value->bank_client_id', $clientId)
                    ->orWhere(function ($query) use ($clientId) {
                        $query->whereNull('value->bank_client_id')->where('value->client_id', $clientId);
                    });
            })
            ->first();
    }

    public function latestPaginated(int $perPage = 10, ?string $search = null, ?string $mode = null): LengthAwarePaginator
    {
        return PaymentRepository::query()
            ->when($search, function ($query, $search) {
                $query->where('key', 'like', "%{$search}%")
                    ->orWhere('payment_gateway_id', 'like', "%{$search}%")
                    ->orWhere('value', 'like', "%{$search}%");
            })
            ->when($mode && $mode !== 'all', fn($query) => $query->where('mode', $mode))
            ->latest()
            ->paginate($perPage);
    }
}
