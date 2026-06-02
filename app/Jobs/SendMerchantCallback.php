<?php

namespace App\Jobs;

use App\Http\Helper\RequestHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMerchantCallback implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $token,
        public array $params,
        public string $urlCallback
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        RequestHelper::sendCallback($this->token, $this->params, $this->urlCallback);
    }
}
