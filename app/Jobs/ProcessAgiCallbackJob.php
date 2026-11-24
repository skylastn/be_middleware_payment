<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Http\Helper\LogHelper;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Repository\Payment\OrderHistoryRepository;
use App\Repository\Payment\OrderRepository;
use App\Services\Payment\BankAgiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persists an AGI callback outside the HTTP request so the bank never waits
 * on database row locks: the HTTP layer validates the payload and replies
 * immediately, while this job performs the order update + history write.
 */
#[WithoutRelations]
class ProcessAgiCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [2, 5, 10, 30];

    public function __construct(
        public array $payload,
        public array $headers,
        public string $rawBody,
    ) {
        //
    }

    public function handle(BankAgiService $service): void
    {
        // Rebuild a request-like object from the stored payload. The stored
        // signature covers the exact raw body, method and path, so it stays
        // valid when re-verified inside the worker.
        $request = \Illuminate\Http\Request::create(
            '/api/bank/agi/notify-qris',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $this->rawBody,
        );
        $request->headers->add($this->headers);

        $repository = $service->authenticateCallbackForJob($request);
        $order = app(OrderRepository::class)->findByGatewayBillNumber(
            $repository->getAttribute('id'),
            (string) $request->input('additionalInfo.billNumber'),
        );
        if (! $order) {
            return;
        }

        $status = match ($request->input('latestTransactionStatus')) {
            '00' => OrderStatus::SUCCESS,
            '05', '06' => OrderStatus::FAILED,
            '01', '02', '03' => OrderStatus::PENDING,
            default => null,
        };
        $previousStatus = $order->getStatus();
        if ($status === null || $previousStatus === $status || $previousStatus === OrderStatus::SUCCESS) {
            return;
        }

        DB::transaction(function () use ($request, $order, $status, $previousStatus): void {
            $order->setCallback($request->getContent());
            $order->setStatus($status);
            $order->save();
            app(OrderHistoryRepository::class)->log($order, $status, 'WEBHOOK_AGI', 'AGI notification received', $request->all(), $previousStatus);

            $project = $order->getRelationValue('project');
            if ($project && $project->getCallback()) {
                dispatch(new SendMerchantCallback($project->getValue(), [
                    'merchantOrderId' => $order->getMerchantOrderId(),
                    'reference' => $order->getReference(),
                    'paymentCode' => $order->getPaymentMethod(),
                    'amount' => $order->getAmount(),
                    'status' => $status->value,
                    'resultCode' => $status === OrderStatus::SUCCESS ? '00' : '01',
                ], $project->getCallback()))->afterCommit();
            }
            dispatch(new SendNotificationJob($order->getReference()))->afterCommit();
        }, 3);
    }

    public function failed(Throwable $exception): void
    {
        \App\Http\Helper\LogHelper::sendErrorLog($exception, [
            'operation' => 'agi_callback_queue',
            'billNumber' => $this->payload['additionalInfo']['billNumber'] ?? null,
        ], '', 'error_agi_callback_queue');
    }
}
