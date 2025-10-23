<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Models\PaymentGateway;
use App\Models\PaymentRepository;
use Exception;

class PaymentRepositoryService
{
    public function getById($id): PaymentRepository
    {
        return PaymentRepository::findOrFailCustom($id);
    }

    public function getByPaymentGatewayKey(string $key,  string $mode): ?PaymentRepository
    {
        $pg = PaymentGateway::where('key', $key)->first();
        if (!FormatHelper::isNotEmpty($pg)) {
            throw new Exception('Payment Gateway Not Found');
        }
        return PaymentRepository::where('payment_gateway_id', $pg->id)->where('mode', $mode)->first();
    }
}
