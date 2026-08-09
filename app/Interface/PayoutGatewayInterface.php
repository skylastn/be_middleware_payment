<?php

namespace App\Interface;

use App\Model\Entity\Payout;
use Illuminate\Http\Request;

interface PayoutGatewayInterface
{
    public function execute(Payout $payout, array $bankDetails, string $mode): array;

    public function handleWebhook(Request $request): array;
}
