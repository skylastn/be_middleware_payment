<?php

namespace App\Services\Payment;

use App\Http\Helper\FormatHelper;
use App\Models\Order;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use App\Models\Project;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class PaymentService
{
    private SPNPayService $spnPayService;
    public function __construct()
    {
        $this->spnPayService = new SPNPayService();
    }

    public function getListPaymentCategory(): Collection
    {
        $result = PaymentCategory::get();
        return $result;
    }

    public function getListPaymentMethod(Request $request): Collection
    {
        $categoriesKey  = $request->categoriesKey;
        $from           = $request->from;
        $result         = PaymentMethod::when($categoriesKey, function ($query) use ($categoriesKey) {
            return $query->whereIn('key', $categoriesKey);
        })->when($from, function ($query) use ($from) {
            return $query->where('from', $from);
        })->get();
        return $result;
    }

    public function getDetailPaymentMethod(?string $value, ?string $from): PaymentMethod
    {
        $result         = PaymentMethod::when($value, function ($query) use ($value) {
            return $query->where('value', $value);
        })->when($from, function ($query) use ($from) {
            return $query->where('from', $from);
        })->first();
        return $result;
    }

    public function createPayment(Request $request): ?array
    {
        $result = null;
        if (env('PAYMENT_APP_KEY') != request()->header('Key')) {
            throw new Exception("Unauthorized", 403);
        }
        $order  = Order::where('reference', $request->reference)->latest()->first();
        if (!FormatHelper::isNotEmpty($order)) {
            throw new Exception("Order Not Found", 403);
        }
        $project = Project::where('type', $order->type)->first();
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
        if ($project->slug == "spnpay") {
            $result = $this->spnPayService->createOrderPaymentSPNPay($request, $project, $order);
        }
        return $result;
    }
}
