<?php

namespace App\Jobs;

use App\Services\Payment\DuitkuService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileDuitkuPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 30;
    public int $backoff = 60;

    public function __construct(public string $orderId) {}

    public function handle(): void
    {
        (new DuitkuService)->reconcile($this->orderId);
    }
}
