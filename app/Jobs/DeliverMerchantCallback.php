<?php

namespace App\Jobs;

use App\Services\Payment\CallbackDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverMerchantCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [10, 30, 120, 300];

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        (new CallbackDeliveryService)->deliver($this->deliveryId);
    }
}
