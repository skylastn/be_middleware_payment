<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Http\Helper\FormatHelper;
use App\Jobs\SendMerchantCallback;
use App\Jobs\SendNotificationJob;
use App\Model\Entity\Order;
use App\Model\Entity\Project;
use App\Repository\Payment\OrderRepository;
use App\Services\System\ProjectService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    private ProjectService $projectService;

    private OrderRepository $orders;

    private OrderHistoryService $orderHistoryService;

    public function __construct(
        ?ProjectService $projectService = null,
        ?OrderRepository $orders = null,
        ?OrderHistoryService $orderHistoryService = null
    ) {
        $this->projectService = $projectService ?? new ProjectService;
        $this->orders = $orders ?? new OrderRepository;
        $this->orderHistoryService = $orderHistoryService ?? new OrderHistoryService;
    }

    public function createAndFind(array $data): Order
    {
        return $this->orders->createAndFind($data);
    }

    public function findOrFailCustom(int|string $id): Order
    {
        return $this->orders->findOrFailCustom($id);
    }

    public function findRecentByReference(string $reference, string $fromDate, string $toDate): ?Order
    {
        return $this->orders->findRecentByReference($reference, $fromDate, $toDate);
    }

    public function findOneByValue(string $value): ?Order
    {
        return $this->orders->findOneByValue($value);
    }

    public function getListOrder(Request $request): LengthAwarePaginator
    {
        $filters = $this->resolveFilters($request);

        return $this->orders->latestPaginated(
            $filters['perPage'],
            $filters['search'],
            $filters['mode'],
            $filters['status'],
            $filters['type'],
            $filters['startDate'],
            $filters['endDate'],
            $filters['paymentRepositoryId'],
            $filters['email'],
            $filters['hour'],
            $filters['startTime'],
            $filters['endTime']
        );
    }

    /**
     * @return array{total_orders: int, total_amount: float, average_amount: float, status_breakdown: array<string, array{count: int, amount: float}>}
     */
    public function getSummaryOrder(Request $request): array
    {
        $filters = $this->resolveFilters($request);

        return $this->orders->summary(
            $filters['search'],
            $filters['mode'],
            $filters['status'],
            $filters['type'],
            $filters['startDate'],
            $filters['endDate'],
            $filters['paymentRepositoryId'],
            $filters['email'],
            $filters['hour'],
            $filters['startTime'],
            $filters['endTime']
        );
    }

    /**
     * @return array{perPage: int, search: ?string, mode: ?string, status: ?string, type: ?string, startDate: ?string, endDate: ?string, paymentRepositoryId: ?string, email: ?string, hour: string|int|null, startTime: string|int|null, endTime: string|int|null}
     */
    private function resolveFilters(Request $request): array
    {
        $hour = $request->query('hour') ?? $request->query('jam');

        return [
            'perPage' => (int) ($request->query('per_page', $request->query('perPage', 10))),
            'search' => $request->query('search'),
            'mode' => $request->query('mode'),
            'status' => $request->query('status'),
            'type' => auth('sanctum')->user()?->isAdmin()
                ? $request->query('type')
                : $this->projectService->checkKey()->type,
            'startDate' => $request->query('start_date') ?: $request->query('startDate'),
            'endDate' => $request->query('end_date') ?: $request->query('endDate'),
            'paymentRepositoryId' => $request->query('payment_repository_id') ?: $request->query('paymentRepositoryId'),
            'email' => $this->normalizeEmailFilter($request),
            'hour' => ($hour !== '' && $hour !== null) ? $hour : null,
            'startTime' => ($request->query('start_time') ?: $request->query('startTime') ?: $request->query('start_hour') ?: $request->query('startHour')) ?: null,
            'endTime' => ($request->query('end_time') ?: $request->query('endTime') ?: $request->query('end_hour') ?: $request->query('endHour')) ?: null,
        ];
    }

    /**
     * Accepts `email=a@x.com,b@y.com`, `email[]=a@x.com&email[]=b@y.com`,
     * or a mix of both, and normalizes to a comma separated string.
     */
    private function normalizeEmailFilter(Request $request): ?string
    {
        $raw = $request->query('email', $request->query('order_email'));
        if ($raw === null || $raw === '') {
            return null;
        }

        $parts = [];
        foreach (is_array($raw) ? $raw : [$raw] as $value) {
            foreach (explode(',', (string) $value) as $piece) {
                $piece = trim($piece);
                if ($piece !== '' && ! in_array($piece, $parts, true)) {
                    $parts[] = $piece;
                }
            }
        }

        return $parts === [] ? null : implode(',', $parts);
    }

    public function detailByReferenceAndKey(string $reference, ?string $projectType): ?Order
    {
        return $this->orders->latestByReference($reference, $projectType);
    }

    public function getOrderById(int|string $id): ?Order
    {
        return $this->orders->findById($id, ['histories']);
    }

    public function deleteOrder(int|string $id): bool
    {
        $order = $this->orders->findById($id);
        if (! $order) {
            throw new Exception('Order Not Found', 404);
        }

        return $this->orders->delete($order);
    }

    private function extractCallbackParams(Order $order): array
    {
        return [
            'merchantOrderId' => $order->getMerchantOrderId() ?: $order->reference,
            'reference' => $order->reference,
            'paymentCode' => $order->getPaymentMethod(),
            'amount' => (int) $order->getAmount(),
            'status' => OrderStatus::SUCCESS->value,
            'resultCode' => '00',
        ];
    }

    public function setSuccessMerchant(Request $request): ?Order
    {
        $project = $this->projectService->checkKey();
        $order = $this->detailByReferenceAndKey($request->reference, $project->type);
        if (! FormatHelper::isNotEmpty($order)) {
            throw new Exception('Order Not Found');
        }

        $reference = $order->getReference();
        $split = explode('-', $reference);
        $project ??= $order->project ?: $this->projectService->getByType($order->type);
        if (! FormatHelper::isNotEmpty($project)) {
            throw new Exception('Project Not Found');
        }

        $order = $this->findOrFailCustom($order->getId());

        $currentStatus = $order->getStatus();
        if ($currentStatus !== null && $currentStatus->isSuccess()) {
            return $order;
        }

        $previousStatus = $order->status;
        $order->setStatus(OrderStatus::SUCCESS);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::SUCCESS,
            'SET_SUCCESS_MERCHANT',
            'Order status manually marked as SUCCESS by merchant request',
            $request->all(),
            $previousStatus
        );

        $params = $this->extractCallbackParams($order);
        $params['status'] = OrderStatus::SUCCESS->value;
        $params['resultCode'] = '00';

        SendMerchantCallback::dispatch($project->value, $params, $project->callback);
        SendNotificationJob::dispatch($reference);

        return $order;
    }

    public function setSuccessById(int|string $id): Order
    {
        $order = $this->orders->findById($id, ['histories']);
        if (! $order) {
            throw new Exception('Order Not Found', 404);
        }

        $currentStatus = $order->getStatus();
        if ($currentStatus !== null && $currentStatus->isSuccess()) {
            return $order;
        }

        $project = $order->project ?: $this->projectService->getByType($order->type);
        if (! FormatHelper::isNotEmpty($project)) {
            throw new Exception('Project Not Found', 404);
        }

        $previousStatus = $order->status;
        $order->setStatus(OrderStatus::SUCCESS);
        $order->save();

        $this->orderHistoryService->log(
            $order,
            OrderStatus::SUCCESS,
            'SET_SUCCESS_ADMIN',
            'Order status manually marked as SUCCESS by admin',
            ['updated_by' => auth('sanctum')->user()?->email ?? 'admin'],
            $previousStatus
        );

        $params = $this->extractCallbackParams($order);
        $params['status'] = OrderStatus::SUCCESS->value;
        $params['resultCode'] = '00';

        if ($project->value && $project->callback) {
            SendMerchantCallback::dispatch($project->value, $params, $project->callback);
        }

        if (FormatHelper::isNotEmpty($order->reference)) {
            SendNotificationJob::dispatch($order->reference);
        }

        return $order->refresh();
    }
}
