<?php

namespace App\Services;

use App\Enums\ProjectSlug;
use App\Http\Helper\FormatHelper;
use App\Models\Order;
use App\Models\Project;
use App\Services\Payment\DuitkuService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\SPNPayService;
use App\Services\Payment\XenditService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

class OrderService
{
    private ProjectService $projectService;
    private XenditService $xenditService;
    private DuitkuService $duitkuService;
    private MidtransService $midtransService;
    private SPNPayService $spnPayService;
    public function __construct()
    {
        $this->projectService = new ProjectService();
        $this->xenditService = new XenditService();
        $this->duitkuService = new DuitkuService();
        $this->midtransService = new MidtransService();
        $this->spnPayService = new SPNPayService();
    }

    public function getListOrder(Request $request): LengthAwarePaginator
    {
        $project = $this->projectService->checkKey();
        return Order::where('type', $project->type)->latest()->paginate($request->perPage);;
    }

    public function detailByReferenceAndKey(string $reference, ?string $projectType): ?Order
    {
        return Order::where('reference', $reference)
            ->when($projectType, function ($query) use ($projectType) {
                $query->where('type', $projectType);
            })
            ->latest()->first();
    }

    public function checkOrderStatus(string $reference): ?stdClass
    {
        $project        = $this->projectService->checkKey();
        $order          = $this->detailByReferenceAndKey($reference, $project->type);
        if (!FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order Not Found');
        }
        $slug       = ProjectSlug::fromName($project->slug);
        $result = null;
        switch ($slug) {
            case ProjectSlug::DUITKU:
                $result =  $this->duitkuService->checkStatus($order);
                break;
            default:
                // $response['message']    = "Undefined Project";
                throw new Exception('Undefined Project');
                break;
        }
        return $result;
    }

    public function create(Request $request): ?array
    {
        $project        = $this->projectService->checkKey();
        if ($project->getSlug() == ProjectSlug::XENDIT) {
            return $this->xenditService->order($request, $project);
        }
        if ($project->getSlug() == ProjectSlug::MIDTRANS) {
            return $this->midtransService->orderMidtrans($request, $project);
        }
        if ($project->slug == ProjectSlug::DUITKU) {
            return  $this->duitkuService->orderDuitku($request, $project);
        }
        if ($project->slug == ProjectSlug::SPNPAY) {
            return $this->spnPayService->createOrderSPNPay($request, $project);
        }
        throw new Exception('Undefined Project');
    }
}
