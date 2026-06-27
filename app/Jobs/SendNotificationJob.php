<?php

namespace App\Jobs;

use App\Services\Payment\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public string $reference
    ) {
        //
    }

    public function handle(): void
    {
        NotificationService::sendNotification($this->reference);
    }
}
