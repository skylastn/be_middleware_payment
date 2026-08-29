<?php

namespace App\Services\Payment;

use App\Enums\ProjectSlug;
use App\Http\Helper\FormatHelper;
use App\Model\Entity\Order;
use App\Repository\Payment\OrderRepository;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

class OrderService
{
    private ProjectService $projectService;

    private OrderRepository $orders;

    private XenditService $xenditService;

    private DuitkuService $duitkuService;

    private MidtransService $midtransService;

    private SPNPayService $spnPayService;

    private StripeService $stripeService;

    public function __construct()
    {
        $this->projectService = new ProjectService;
        $this->orders = new OrderRepository;
        $this->xenditService = new XenditService;
        $this->duitkuService = new DuitkuService;
        $this->midtransService = new MidtransService;
        $this->spnPayService = new SPNPayService;
        $this->stripeService = new StripeService;
    }

    public function getListOrder(Request $request): LengthAwarePaginator
    {
        $search = $request->query('search');
        $mode = $request->query('mode');
        $status = $request->query('status');
        $perPage = (int) ($request->query('per_page', $request->query('perPage', 10)));
        $startDate = $request->query('start_date') ?: $request->query('startDate');
        $endDate = $request->query('end_date') ?: $request->query('endDate');
        $paymentRepositoryId = $request->query('payment_repository_id') ?: $request->query('paymentRepositoryId');

        $type = auth('sanctum')->user()?->isAdmin()
            ? $request->query('type')
            : $this->projectService->checkKey()->type;

        return $this->orders->latestPaginated(
            $perPage,
            $search,
            $mode,
            $status,
            $type,
            $startDate,
            $endDate,
            $paymentRepositoryId
        );
    }

    public function detailByReferenceAndKey(string $reference, ?string $projectType): ?Order
    {
        return $this->orders->latestByReference($reference, $projectType);
    }

    public function checkOrderStatus(string $reference): ?stdClass
    {
        $project = $this->projectService->checkKey();
        $order = $this->detailByReferenceAndKey($reference, $project->type);
        if (! FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order Not Found');
        }
        $slug = $project->getSlug();
        $result = null;
        switch ($slug) {
            case ProjectSlug::DUITKU:
                $result = $this->duitkuService->checkStatus($order);
                break;
            case ProjectSlug::STRIPE:
                $result = $this->stripeService->checkStatus($order);
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
        $project = $this->projectService->checkKey();
        if ($project->getSlug() == ProjectSlug::XENDIT) {
            return $this->xenditService->order($request, $project);
        }
        if ($project->getSlug() == ProjectSlug::MIDTRANS) {
            return $this->midtransService->orderMidtrans($request, $project);
        }
        if ($project->slug == ProjectSlug::DUITKU) {
            return $this->duitkuService->orderDuitku($request, $project);
        }
        if ($project->slug == ProjectSlug::SPNPAY) {
            return $this->spnPayService->createOrderSPNPay($request, $project);
        }
        if ($project->slug == ProjectSlug::STRIPE) {
            return $this->stripeService->order($request, $project);
        }
        throw new Exception('Undefined Project');
    }

    public function confirmStripe(Request $request): ?array
    {
        $project = $this->projectService->checkKey();
        if ($project->getSlug() == ProjectSlug::STRIPE) {
            return $this->stripeService->confirmCardPayment($request, $project);
        }
        throw new Exception('Stripe confirm only supported for Stripe projects');
    }
}
