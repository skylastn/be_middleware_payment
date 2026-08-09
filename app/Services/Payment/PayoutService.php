<?php

namespace App\Services\Payment;

use App\Enums\PayoutGateway;
use App\Enums\TransferStatus;
use App\Http\Helper\PayoutReferenceGenerator;
use App\Interface\PayoutGatewayInterface;
use App\Jobs\SendPayoutCallback;
use App\Model\Entity\Payout;
use App\Model\Entity\PayoutHistory;
use App\Model\Entity\Project;
use Exception;
use Illuminate\Http\Request;

class PayoutService
{
    private PaymentRepositoryService $paymentRepositoryService;

    public function __construct()
    {
        $this->paymentRepositoryService = new PaymentRepositoryService;
    }

    public function create(
        Project $project,
        float $amount,
        array $bankDetails,
        string $gateway = 'stripe',
        string $currency = 'myr',
        string $mode = 'sandbox',
        ?string $callerReference = null,
        ?string $callbackUrl = null,
    ): array {
        $gatewayEnum = PayoutGateway::fromName($gateway)
            ?? throw new Exception('Unsupported payout gateway: '.$gateway);

        $ref = PayoutReferenceGenerator::generate($project->type);

        $requestPayload = [
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gateway,
            'mode' => $mode,
            'caller_reference' => $callerReference,
            'callback_url' => $callbackUrl,
            'callback_token' => $project->getValue(),
            'bank_details' => $bankDetails,
        ];

        $payout = Payout::create([
            'gateway' => $gatewayEnum,
            'amount' => $amount,
            'currency' => strtolower($currency),
            'status' => TransferStatus::PENDING,
            'reference' => $ref['reference'],
            'internal_id' => $callerReference,
            'request' => json_encode($requestPayload),
        ]);

        $this->logHistory($payout, 'created', TransferStatus::PENDING, 'Payout request received.');

        try {
            $gatewayService = $this->resolveGateway($gatewayEnum);
            $result = $gatewayService->execute($payout, $bankDetails, $mode);

            $payout->update([
                'status' => TransferStatus::PROCESSING,
                'response' => json_encode($result),
            ]);

            $this->logHistory($payout, 'gateway_processing', TransferStatus::PROCESSING, 'Payout submitted to gateway, awaiting confirmation.');

            return [
                'transfer_id' => $result['transfer_id'] ?? null,
                'reference' => $ref['reference'],
                'status' => 'processing',
            ];
        } catch (Exception $e) {
            $payout->update([
                'status' => TransferStatus::FAILED,
                'response' => json_encode(['error' => $e->getMessage()]),
            ]);

            $this->logHistory($payout, 'gateway_failed', TransferStatus::FAILED, $e->getMessage());

            $this->sendCallback($payout, false, $e->getMessage());

            throw $e;
        }
    }

    public function handleWebhook(Request $request): void
    {
        $reference = $request->input('reference')
            ?? $request->input('data.object.reference')
            ?? $request->input('data.object.metadata.reference');

        if (empty($reference)) {
            throw new Exception('Reference is required in webhook payload.');
        }

        $payout = Payout::where('reference', $reference)->first();

        if (! $payout) {
            throw new Exception('Payout not found: '.$reference);
        }

        $status = $request->input('status')
            ?? $request->input('data.object.status')
            ?? $request->input('type');

        $isSuccess = $status === 'paid'
            || $status === 'succeeded'
            || $status === 'payout.paid'
            || $request->input('result_code') === '00';

        if ($isSuccess) {
            $payout->update([
                'status' => TransferStatus::SUCCESS,
                'callback' => json_encode($request->all()),
                'completed_at' => now(),
            ]);
            $this->logHistory($payout, 'webhook_success', TransferStatus::SUCCESS, 'Webhook confirmed payout success.');
            $this->sendCallback($payout, true);
        } else {
            $payout->update([
                'status' => TransferStatus::FAILED,
                'callback' => json_encode($request->all()),
                'completed_at' => now(),
            ]);
            $this->logHistory($payout, 'webhook_failed', TransferStatus::FAILED, 'Webhook reported payout failure.');
            $this->sendCallback($payout, false, 'Payout failed via webhook.');
        }
    }

    public function handleCallback(string $reference, array $payload): void
    {
        $payout = Payout::where('reference', $reference)->first();

        if (! $payout) {
            throw new Exception('Payout not found: '.$reference);
        }

        $payout->update([
            'callback' => json_encode($payload),
        ]);

        $resultCode = $payload['result_code'] ?? '';
        $success = $resultCode === '00';

        if ($success) {
            $payout->update([
                'status' => TransferStatus::SUCCESS,
                'completed_at' => now(),
            ]);
            $this->logHistory($payout, 'callback_success', TransferStatus::SUCCESS, 'Callback confirmed success.');
        } else {
            $payout->update([
                'status' => TransferStatus::FAILED,
                'completed_at' => now(),
            ]);
            $this->logHistory($payout, 'callback_failed', TransferStatus::FAILED, 'Callback reported failure.');
        }
    }

    private function sendCallback(Payout $payout, bool $success, string $errorMessage = ''): void
    {
        $requestData = json_decode((string) ($payout->request ?? '{}'), true) ?? [];
        $callbackUrl = (string) ($requestData['callback_url'] ?? '');
        $callerReference = (string) ($requestData['caller_reference'] ?? '');
        $token = (string) ($requestData['callback_token'] ?? '');

        if ($callbackUrl === '') {
            return;
        }

        $params = [
            'reference' => $callerReference ?: $payout->reference,
            'external_id' => $payout->reference,
            'result_code' => $success ? '00' : '99',
            'status' => $success ? 'success' : 'failed',
            'message' => $success ? 'Payout completed.' : ($errorMessage ?: 'Payout failed.'),
        ];

        SendPayoutCallback::dispatch($token, $params, $callbackUrl)->afterCommit();
    }

    private function resolveGateway(PayoutGateway $gateway): PayoutGatewayInterface
    {
        return match ($gateway) {
            PayoutGateway::Stripe => new StripePayoutService($this->paymentRepositoryService),
            PayoutGateway::Duitku,
            PayoutGateway::Xendit,
            PayoutGateway::Midtrans => throw new Exception('Payout gateway not implemented yet: '.$gateway->value),
        };
    }

    private function logHistory(Payout $payout, string $action, TransferStatus $status, string $message, ?array $metadata = null): void
    {
        PayoutHistory::create([
            'payout_id' => $payout->id,
            'action' => $action,
            'status' => $status->value,
            'message' => $message,
            'metadata' => $metadata,
            'performed_at' => now(),
        ]);
    }
}
