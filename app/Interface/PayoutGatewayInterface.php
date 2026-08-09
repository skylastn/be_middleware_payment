<?php

namespace App\Interface;

use App\Model\Entity\Payout;

interface PayoutGatewayInterface
{
    public function execute(Payout $payout, array $bankDetails, string $mode): array;
}
