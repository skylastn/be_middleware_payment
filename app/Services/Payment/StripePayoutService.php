<?php

namespace App\Services\Payment;

use App\Http\Helper\LogHelper;
use App\Interface\PayoutGatewayInterface;
use App\Model\Entity\Payout;
use Exception;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Payout as StripePayout;
use Stripe\Stripe;

class StripePayoutService implements PayoutGatewayInterface
{
    private PaymentRepositoryService $paymentRepositoryService;

    public function __construct(PaymentRepositoryService $paymentRepositoryService)
    {
        $this->paymentRepositoryService = $paymentRepositoryService;
    }

    public function execute(Payout $payout, array $bankDetails, string $mode): array
    {
        $paymentRepo = $this->paymentRepositoryService->getByPaymentGatewayKey('stripe', $mode);

        if (! $paymentRepo) {
            throw new Exception('Stripe Payment Repository Not Found');
        }

        $secretKey = $paymentRepo->getValue()['stripe_secretkey'] ?? '';

        if (empty($secretKey)) {
            throw new Exception('Stripe Secret Key Not Configured');
        }

        Stripe::setApiKey($secretKey);

        $amountInCents = (int) round($payout->amount * 100);

        try {
            $stripePayout = StripePayout::create([
                'amount' => $amountInCents,
                'currency' => $payout->currency,
                'description' => 'Payout '.$payout->reference,
                'metadata' => [
                    'reference' => $payout->reference,
                ],
            ]);

            return [
                'transfer_id' => $stripePayout->id,
                'message' => 'Stripe payout created.',
                'gateway' => 'stripe',
                'stripe_response' => $stripePayout->toArray(),
            ];
        } catch (ApiErrorException $e) {
            $message = match (true) {
                Str::contains($e->getMessage(), 'insufficient funds')
                    || Str::contains($e->getMessage(), 'balance') => 'Platform balance is insufficient.',
                Str::contains($e->getMessage(), 'routing_number')
                    || Str::contains($e->getMessage(), 'account_number') => 'Invalid bank account details.',
                Str::contains($e->getMessage(), 'currency') => 'Currency not supported.',
                default => 'Stripe payout failed: '.$e->getMessage(),
            };

            LogHelper::sendErrorLog($e);

            throw new Exception($message, $e->getCode(), $e);
        }
    }
}
