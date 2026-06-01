<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentRepository;
use App\Repository\BaseRepository;

class PaymentRepositoryRepository extends BaseRepository
{
    public function findByGatewayAndMode(string $paymentGatewayId, string $mode): ?PaymentRepository
    {
        return PaymentRepository::where('payment_gateway_id', $paymentGatewayId)
            ->where('mode', $mode)
            ->first();
    }

    protected function modelClass(): string
    {
        return PaymentRepository::class;
    }
}
