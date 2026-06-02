<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helper\ResponseHelper;
use App\Jobs\TestQueueJob;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    /**
     * Test endpoint to verify that the queue (Redis + worker) is working.
     *
     * Usage:
     *   GET  /api/test/queue?message=hello
     *   POST /api/test/queue  { "message": "hello" }
     *
     * Optional:
     *   ?after_commit=1   - dispatch inside DB transaction with ->afterCommit()
     *                       (useful to verify afterCommit behavior like SendMerchantCallback)
     *
     * How to verify it worked:
     *   - Endpoint returns immediately with "Job dispatched"
     *   - Check Laravel Telescope: /telescope (Jobs or Queues tab)
     *   - Check logs: the queue worker should log the START / COMPLETED messages
     *     (in Docker: docker compose logs -f middleware-payment | grep -i testqueue)
     *   - In production/local with Redis: you can monitor the queue with redis-cli
     */
    public function testQueue(Request $request): JsonResponse
    {
        try {
            $message = $request->input('message', 'Test queue job from endpoint at ' . now()->toDateTimeString());

            if ($request->boolean('after_commit')) {
                // Test the afterCommit pattern used by real callbacks
                DB::transaction(function () use ($message) {
                    TestQueueJob::dispatch($message)->afterCommit();
                });
                $note = 'dispatched with afterCommit() inside transaction';
            } else {
                TestQueueJob::dispatch($message);
                $note = 'dispatched normally';
            }

            Log::info('TestQueue endpoint: job dispatched - ' . $note, ['message' => $message]);

            return ResponseHelper::successResponse([
                'dispatched' => true,
                'note' => $note,
                'message' => $message,
                'queue_connection' => config('queue.default'),
            ], 'Test job dispatched to queue. Check Telescope or logs to confirm it was processed.');
        } catch (Exception $ex) {
            Log::error('TestQueue endpoint error', ['error' => $ex->getMessage()]);
            return ResponseHelper::failedResponse($ex->getMessage(), 'Failed to dispatch test job', 500, $ex->getLine());
        }
    }
}
