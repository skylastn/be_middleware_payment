<?php

namespace App\Jobs;

use App\Http\Helper\RequestHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPayoutCallback implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 10;

    public function __construct(
        public string $token,
        public array $params,
        public string $urlCallback,
    ) {}

    public function handle(): void
    {
        RequestHelper::sendCallback($this->token, $this->params, $this->urlCallback);
    }
}
