<?php

namespace App\Services\Payment;

use App\Enums\ProjectSlug;
use App\Model\Entity\PaymentRepository;
use Exception;

class GatewayHistoryService
{
    private StripeService $stripeService;
    private XenditService $xenditService;
    private MidtransService $midtransService;
    private DuitkuService $duitkuService;
    private SPNPayService $spnPayService;
    private PaprikaService $paprikaService;

    public function __construct()
    {
        $this->stripeService = new StripeService();
        $this->xenditService = new XenditService();
        $this->midtransService = new MidtransService();
        $this->duitkuService = new DuitkuService();
        $this->spnPayService = new SPNPayService();
        $this->paprikaService = new PaprikaService();
    }

    /**
     * @param PaymentRepository $repository
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function fetchHistory(PaymentRepository $repository, array $filters = []): array
    {
        $gatewayKey = strtolower(trim($repository->payment_gateway?->key ?? $repository->key ?? ''));
        $slug = ProjectSlug::tryFrom($gatewayKey);

        if (! $slug) {
            foreach (ProjectSlug::cases() as $case) {
                if (stripos($gatewayKey, $case->value) !== false) {
                    $slug = $case;
                    break;
                }
            }
        }

        return match ($slug) {
            ProjectSlug::STRIPE => $this->stripeService->fetchHistory($repository, $filters),
            ProjectSlug::XENDIT => $this->xenditService->fetchHistory($repository, $filters),
            ProjectSlug::MIDTRANS => $this->midtransService->fetchHistory($repository, $filters),
            ProjectSlug::DUITKU => $this->duitkuService->fetchHistory($repository, $filters),
            ProjectSlug::SPNPAY => $this->spnPayService->fetchHistory($repository, $filters),
            ProjectSlug::PAPRIKA => $this->paprikaService->fetchHistory($repository, $filters),
            default => throw new Exception("Live transaction history inquiry is not supported for gateway '{$gatewayKey}'"),
        };
    }
}
