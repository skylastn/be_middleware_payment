<?php

namespace App\Repository\Payment;

use App\Model\Entity\Order;
use App\Repository\BaseRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository extends BaseRepository
{
    public function latestPaginated(
        int $perPage = 10,
        ?string $search = null,
        ?string $mode = null,
        ?string $status = null,
        ?string $type = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentRepositoryId = null,
        string|array|null $email = null,
        string|int|null $hour = null,
        string|int|null $startTime = null,
        string|int|null $endTime = null
    ): LengthAwarePaginator {
        return $this->filteredQuery($search, $mode, $status, $type, $startDate, $endDate, $paymentRepositoryId, $email, $hour, $startTime, $endTime)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{total_orders: int, total_amount: float, status_breakdown: array<string, array{count: int, amount: float}>}
     */
    public function summary(
        ?string $search = null,
        ?string $mode = null,
        ?string $status = null,
        ?string $type = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentRepositoryId = null,
        string|array|null $email = null,
        string|int|null $hour = null,
        string|int|null $startTime = null,
        string|int|null $endTime = null
    ): array {
        $query = $this->filteredQuery($search, $mode, $status, $type, $startDate, $endDate, $paymentRepositoryId, $email, $hour, $startTime, $endTime);

        $aggregate = (clone $query)->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(amount), 0) as total_amount')->first();
        $totalOrders = (int) ($aggregate->total_orders ?? 0);
        $totalAmount = (float) ($aggregate->total_amount ?? 0);

        $breakdown = (clone $query)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($row) {
                $status = $row->status instanceof \BackedEnum
                    ? $row->status->value
                    : (string) $row->status;

                return [$status !== '' ? $status : 'UNKNOWN' => ['count' => (int) $row->count, 'amount' => (float) $row->amount]];
            })
            ->all();

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'average_amount' => $totalOrders > 0 ? round($totalAmount / $totalOrders, 2) : 0.0,
            'status_breakdown' => $breakdown,
        ];
    }

    private function filteredQuery(
        ?string $search,
        ?string $mode,
        ?string $status,
        ?string $type,
        ?string $startDate,
        ?string $endDate,
        ?string $paymentRepositoryId,
        string|array|null $email,
        string|int|null $hour,
        string|int|null $startTime,
        string|int|null $endTime
    ): Builder {
        if ($hour !== null && $hour !== '' && $startTime === null && $endTime === null) {
            if (is_string($hour) && str_contains($hour, '-')) {
                [$s, $e] = explode('-', $hour, 2);
                $startTime = trim($s);
                $endTime = trim($e);
            } else {
                $startTime = $hour;
                $endTime = $hour;
            }
        }

        $formattedStart = ($startTime !== null && $startTime !== '') ? $this->normalizeTime($startTime, false) : null;
        $formattedEnd = ($endTime !== null && $endTime !== '') ? $this->normalizeTime($endTime, true) : null;

        return Order::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($mode && $mode !== 'all', fn ($query) => $query->where('mode', $mode))
            ->when($status && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($type && $type !== 'all', fn ($query) => $query->where('type', $type))
            ->when($paymentRepositoryId && $paymentRepositoryId !== 'all', fn ($query) => $query->where('payment_repository_id', $paymentRepositoryId))
            ->when($email, function ($query, $email) {
                $emails = $this->parseEmailList($email);
                if ($emails === []) {
                    return;
                }
                $query->whereIn(DB::raw('LOWER(email)'), array_map('mb_strtolower', $emails));
            })
            ->when($startDate, function ($query, $startDate) {
                $dt = preg_match('/\d{1,2}:\d{2}/', (string) $startDate)
                    ? Carbon::parse($startDate)
                    : Carbon::parse($startDate)->startOfDay();
                $query->where('created_at', '>=', $dt);
            })
            ->when($endDate, function ($query, $endDate) {
                $dt = preg_match('/\d{1,2}:\d{2}/', (string) $endDate)
                    ? Carbon::parse($endDate)
                    : Carbon::parse($endDate)->endOfDay();
                $query->where('created_at', '<=', $dt);
            })
            ->when($formattedStart && $formattedEnd && $formattedStart <= $formattedEnd, function ($query) use ($formattedStart, $formattedEnd) {
                $query->whereTime('created_at', '>=', $formattedStart)
                    ->whereTime('created_at', '<=', $formattedEnd);
            })
            ->when($formattedStart && $formattedEnd && $formattedStart > $formattedEnd, function ($query) use ($formattedStart, $formattedEnd) {
                $query->where(function ($q) use ($formattedStart, $formattedEnd) {
                    $q->whereTime('created_at', '>=', $formattedStart)
                        ->orWhereTime('created_at', '<=', $formattedEnd);
                });
            })
            ->when($formattedStart && ! $formattedEnd, fn ($query) => $query->whereTime('created_at', '>=', $formattedStart))
            ->when(! $formattedStart && $formattedEnd, fn ($query) => $query->whereTime('created_at', '<=', $formattedEnd));
    }

    private function normalizeTime(string|int $val, bool $isEnd): string
    {
        $val = trim((string) $val);
        if (is_numeric($val)) {
            return sprintf('%02d:%s', (int) $val, $isEnd ? '59:59' : '00:00');
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $val, $m)) {
            return sprintf('%02d:%02d:%s', (int) $m[1], (int) $m[2], $isEnd ? '59' : '00');
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $val, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return $val;
    }

    /**
     * Split a comma separated email filter into unique, trimmed addresses.
     *
     * @return array<int, string>
     */
    private function parseEmailList(string|array $email): array
    {
        $raw = is_array($email) ? $email : explode(',', $email);

        $emails = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item !== '' && ! in_array($item, $emails, true)) {
                $emails[] = $item;
            }
        }

        return $emails;
    }

    public function latestByReference(string $reference, ?string $projectType = null): ?Order
    {
        return Order::where('reference', $reference)
            ->when($projectType, fn ($query) => $query->where('type', $projectType))
            ->latest()
            ->first();
    }

    public function findById(int|string $id, array $with = []): ?Order
    {
        return $this->find($id, $with);
    }

    public function createAndFind(array $data): Order
    {
        return Order::createAndFind($data);
    }

    public function findOrFailCustom(int|string $id): Order
    {
        return Order::findOrFailCustom($id);
    }

    public function findRecentByReference(string $reference, string $fromDate, string $toDate): ?Order
    {
        return Order::where('reference', $reference)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('id', 'desc')
            ->first();
    }

    public function findOneByValue(string $value): ?Order
    {
        return Order::where('value', $value)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function findByGatewayBillNumber(string $repositoryId, string $billNumber): ?Order
    {
        return Order::withoutEagerLoads()->where('payment_repository_id', $repositoryId)
            ->where('gateway_bill_number', $billNumber)
            ->lockForUpdate()
            ->first();
    }

    public function findByIdForUpdate(string $id): ?Order
    {
        return Order::withoutEagerLoads()->where('id', $id)->lockForUpdate()->first();
    }

    protected function modelClass(): string
    {
        return Order::class;
    }
}
