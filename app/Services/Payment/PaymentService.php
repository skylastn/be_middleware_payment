<?php

namespace App\Services\Payment;

use App\Enums\ProjectSlug;
use App\Http\Helper\FormatHelper;
use App\Model\Entity\Order;
use App\Model\Entity\PaymentMethod;
use App\Model\Entity\Project;
use App\Repository\Payment\OrderRepository;
use App\Repository\Payment\PaymentCategoryRepository;
use App\Repository\Payment\PaymentMethodRepository;
use App\Repository\System\ProjectRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PaymentService
{
    private SPNPayService $spnPayService;

    private StripeService $stripeService;

    private XenditService $xenditService;

    private MidtransService $midtransService;

    private DuitkuService $duitkuService;

    private OrderRepository $orders;

    private PaymentCategoryRepository $paymentCategories;

    private PaymentMethodRepository $paymentMethods;

    private ProjectRepository $projects;

    public function __construct()
    {
        $this->spnPayService = new SPNPayService;
        $this->stripeService = new StripeService;
        $this->xenditService = new XenditService;
        $this->midtransService = new MidtransService;
        $this->duitkuService = new DuitkuService;
        $this->orders = new OrderRepository;
        $this->paymentCategories = new PaymentCategoryRepository;
        $this->paymentMethods = new PaymentMethodRepository;
        $this->projects = new ProjectRepository;
    }

    public function getListPaymentCategory(): Collection
    {
        return $this->paymentCategories->all();
    }

    public function getListPaymentMethod(Request $request): Collection
    {
        return $this->paymentMethods->filtered($request->categoriesKey, $request->from);
    }

    public function getDetailPaymentMethod(?string $value, ?string $from): ?PaymentMethod
    {
        return $this->paymentMethods->detail($value, $from);
    }

    public function createPayment(Request $request, Project $project): ?array
    {
        $order = $this->orders->latestByReference($request->reference, $project->type);
        if (! FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order Not Found', 404);
        }

        return $this->processOrderPayment($request, $project, $order);
    }

    public function processOrderPayment(Request $request, Project $project, Order $order): ?array
    {
        $slug = $project->getSlug();

        switch ($slug) {
            // case ProjectSlug::XENDIT:
            //     return $this->xenditService->order($request, $project);
            // case ProjectSlug::MIDTRANS:
            //     return $this->midtransService->orderMidtrans($request, $project);
            // case ProjectSlug::DUITKU:
            //     return $this->duitkuService->orderDuitku($request, $project);
            case ProjectSlug::SPNPAY:
                return $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order);
            case ProjectSlug::STRIPE:
                return $this->stripeService->order($request, $project);
            default:
                throw new Exception('Undefined Project');
        }
    }
}
