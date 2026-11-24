<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Http\Helper\LogHelper;
use App\Repository\Payment\OrderRepository;
use App\Services\Payment\BankAgiService;
use App\Services\Payment\OrderHistoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persists an AGI callback outside the HTTP request so the bank never waits
 * on database row locks: the HTTP layer validates the payload and replies
 * immediately, while this job performs the order update + history write.
 */
class ProcessAgiCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 10, 30];

    public function __construct(
        public string $rawBody,
        public string $orderId,
        public OrderStatus $status,
    ) {
        //
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $order = app(OrderRepository::class)->findByIdForUpdate($this->orderId);
            if (! $order) {
                return;
            }

            $previousStatus = $order->getStatus();
            if (BankAgiService::shouldIgnoreStatusTransition($previousStatus, $this->status)) {
                return;
            }

            $order->setCallback($this->rawBody);
            $order->setStatus($this->status);
            $order->save();
            app(OrderHistoryService::class)->log($order, $this->status, 'WEBHOOK_AGI', 'AGI notification received', $this->rawBody, $previousStatus);

            $project = $order->getRelationValue('project');
            if ($project && $project->getCallback()) {
                dispatch(new SendMerchantCallback($project->getValue(), [
                    'merchantOrderId' => $order->getMerchantOrderId(),
                    'reference' => $order->getReference(),
                    'paymentCode' => $order->getPaymentMethod(),
                    'amount' => $order->getAmount(),
                    'status' => $this->status->value,
                    'resultCode' => $this->status === OrderStatus::SUCCESS ? '00' : '01',
                ], $project->getCallback()))->afterCommit();
            }
            dispatch(new SendNotificationJob($order->getReference()))->afterCommit();
        }, 3);
    }

    public function failed(Throwable $exception): void
    {
        $payload = json_decode($this->rawBody, true);

        LogHelper::sendErrorLog($exception, [
            'operation' => 'agi_callback_queue',
            'billNumber' => is_array($payload) ? ($payload['additionalInfo']['billNumber'] ?? null) : null,
        ], '', 'error_agi_callback_queue');
    }
}
