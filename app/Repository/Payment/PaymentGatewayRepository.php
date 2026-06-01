<?php

namespace App\Repository\Payment;

use App\Model\Entity\PaymentGateway;
use App\Repository\BaseRepository;

class PaymentGatewayRepository extends BaseRepository
{
    public function findByKey(string $key): ?PaymentGateway
    {
        return PaymentGateway::where('key', $key)->first();
    }

    protected function modelClass(): string
    {
        return PaymentGateway::class;
    }
}
