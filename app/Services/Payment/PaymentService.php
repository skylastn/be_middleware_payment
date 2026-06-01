<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Enums\ProjectSlug;
use App\Model\Entity\PaymentMethod;
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
    private OrderRepository $orders;
    private PaymentCategoryRepository $paymentCategories;
    private PaymentMethodRepository $paymentMethods;
    private ProjectRepository $projects;

    public function __construct()
    {
        $this->spnPayService = new SPNPayService();
        $this->orders = new OrderRepository();
        $this->paymentCategories = new PaymentCategoryRepository();
        $this->paymentMethods = new PaymentMethodRepository();
        $this->projects = new ProjectRepository();
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

    public function createPayment(Request $request): ?array
    {
        $result = null;
        if (env('PAYMENT_APP_KEY') != request()->header('Key')) {
            throw new Exception("Unauthorized", 403);
        }
        $order = $this->orders->latestByReference($request->reference);
        if (!FormatHelper::isNotEmpty($order)) {
            throw new Exception("Order Not Found", 403);
        }
        $project = $this->projects->findByType($order->type);
        if (!FormatHelper::isNotEmpty($project)) {
            throw new Exception("Project Not Found", 403);
        }
        // if ($project->slug == "xendit") {
        //     return $this->orderXendit($request, $project);
        // }
        // if ($project->slug == "midtrans") {
        //     return $this->orderMidtrans($request, $project);
        // }
        // if ($project->slug == "duitku") {
        //     return DuitkuService::orderDuitku($request, $project);
        // }
        if ($project->getSlug() === ProjectSlug::SPNPAY) {
            $result = $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order);
        }
        return $result;
    }
}
