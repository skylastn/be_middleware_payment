<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TestQueueJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $message = 'Test queue job executed'
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('TestQueueJob START: ' . $this->message . ' | time=' . now()->toDateTimeString());

        // Simulate some processing work so you can observe it is async
        sleep(2);

        Log::info('TestQueueJob COMPLETED: ' . $this->message . ' | time=' . now()->toDateTimeString());
    }
}
