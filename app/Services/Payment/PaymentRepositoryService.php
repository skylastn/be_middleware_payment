<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Model\Entity\PaymentRepository;
use App\Repository\Payment\PaymentGatewayRepository;
use App\Repository\Payment\PaymentRepositoryRepository;
use Exception;

class PaymentRepositoryService
{
    public function __construct(
        private ?PaymentGatewayRepository $paymentGateways = null,
        private ?PaymentRepositoryRepository $paymentRepositories = null,
    ) {
        $this->paymentGateways ??= new PaymentGatewayRepository();
        $this->paymentRepositories ??= new PaymentRepositoryRepository();
    }

    public function getById($id): PaymentRepository
    {
        return PaymentRepository::findOrFailCustom($id);
    }

    public function getByPaymentGatewayKey(string $key,  string $mode): ?PaymentRepository
    {
        $pg = $this->paymentGateways->findByKey($key);
        if (!FormatHelper::isNotEmpty($pg)) {
            throw new Exception('Payment Gateway Not Found');
        }
        return $this->paymentRepositories->findByGatewayAndMode($pg->id, $mode);
    }
}
