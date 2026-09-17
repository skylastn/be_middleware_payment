<?php

namespace App\Services\System;

use App\Interface\RedisServiceInterface;
use App\Jobs\TestQueueJob;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QueueMonitorService
{
    public function __construct(private ?RedisServiceInterface $redisService = null)
    {
        $this->redisService = $redisService ?? app(RedisServiceInterface::class);
    }

    public function getOverview(): array
    {
        $driver = config('queue.default', 'redis');
        $defaultQueue = config("queue.connections.{$driver}.queue", 'default');

        $pending = 0;
        $scheduled = 0;
        $reserved = 0;
        $failed = 0;
        $details = [];

        // 1. Failed jobs count
        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
        }

        // 2. Metrics based on driver
        if ($driver === 'redis') {
            try {
                $pending = (int) Redis::llen("queues:{$defaultQueue}");
                $scheduled = (int) Redis::zcard("queues:{$defaultQueue}:delayed");
                $reserved = (int) Redis::zcard("queues:{$defaultQueue}:reserved");

                $clientInfo = Redis::connection()->client()->info();
                $details = [
                    'redis_version' => $clientInfo['Server']['redis_version'] ?? 'unknown',
                    'uptime_in_days' => $clientInfo['Server']['uptime_in_days'] ?? 0,
                    'connected_clients' => $clientInfo['Clients']['connected_clients'] ?? 0,
                    'used_memory_human' => $clientInfo['Memory']['used_memory_human'] ?? '0M',
                ];
            } catch (\Throwable $e) {
                $details['error'] = $e->getMessage();
            }
        } elseif ($driver === 'database') {
            if (Schema::hasTable('jobs')) {
                $now = time();
                $pending = (int) DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', $now)->count();
                $scheduled = (int) DB::table('jobs')->where('available_at', '>', $now)->count();
                $reserved = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
            }
        } elseif ($driver === 'rabbitmq') {
            $details = [
                'host' => config('queue.connections.rabbitmq.hosts.0.host', 'localhost'),
                'port' => config('queue.connections.rabbitmq.hosts.0.port', 5672),
                'vhost' => config('queue.connections.rabbitmq.hosts.0.vhost', '/'),
                'note' => 'RabbitMQ driver active via AMQP protocol',
            ];
        }

        return [
            'driver' => $driver,
            'default_queue' => $defaultQueue,
            'stats' => [
                'pending' => $pending,
                'scheduled' => $scheduled,
                'reserved' => $reserved,
                'failed' => $failed,
                'total_in_queue' => $pending + $scheduled + $reserved,
            ],
            'details' => $details,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function getActiveJobs(string $type = 'all', int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        $driver = config('queue.default', 'redis');
        $defaultQueue = config("queue.connections.{$driver}.queue", 'default');
        $items = [];

        if ($driver === 'redis') {
            try {
                // Pending (List)
                if ($type === 'all' || $type === 'pending') {
                    $rawPending = Redis::lrange("queues:{$defaultQueue}", 0, 99) ?: [];
                    foreach ($rawPending as $raw) {
                        $parsed = $this->parseJobPayload($raw, 'pending', $defaultQueue);
                        if ($parsed) {
                            $items[] = $parsed;
                        }
                    }
                }

                // Scheduled / Delayed (Sorted Set)
                if ($type === 'all' || $type === 'scheduled') {
                    $rawScheduled = Redis::zrange("queues:{$defaultQueue}:delayed", 0, 99, ['WITHSCORES' => true]) ?: [];
                    foreach ($rawScheduled as $raw => $score) {
                        $parsed = $this->parseJobPayload($raw, 'scheduled', $defaultQueue, (int) $score);
                        if ($parsed) {
                            $items[] = $parsed;
                        }
                    }
                }

                // In-Flight / Reserved (Sorted Set)
                if ($type === 'all' || $type === 'reserved') {
                    $rawReserved = Redis::zrange("queues:{$defaultQueue}:reserved", 0, 99, ['WITHSCORES' => true]) ?: [];
                    foreach ($rawReserved as $raw => $score) {
                        $parsed = $this->parseJobPayload($raw, 'reserved', $defaultQueue, (int) $score);
                        if ($parsed) {
                            $items[] = $parsed;
                        }
                    }
                }
            } catch (\Throwable) {
                $items = [];
            }
        } elseif ($driver === 'database' && Schema::hasTable('jobs')) {
            $now = time();
            $query = DB::table('jobs');
            if ($type === 'pending') {
                $query->whereNull('reserved_at')->where('available_at', '<=', $now);
            } elseif ($type === 'scheduled') {
                $query->where('available_at', '>', $now);
            } elseif ($type === 'reserved') {
                $query->whereNotNull('reserved_at');
            }

            $dbPaginator = $query->orderBy('available_at', 'asc')->paginate($perPage, ['*'], 'page', $page);
            $transformed = $dbPaginator->getCollection()->map(function ($row) use ($now) {
                $status = $row->reserved_at !== null ? 'reserved' : ($row->available_at > $now ? 'scheduled' : 'pending');
                $parsed = $this->parseJobPayload($row->payload, $status, $row->queue, $row->available_at);
                if ($parsed) {
                    $parsed['id'] = (string) $row->id;
                    $parsed['attempts'] = (int) $row->attempts;
                }

                return $parsed;
            })->filter()->values();

            return new LengthAwarePaginator(
                $transformed,
                $dbPaginator->total(),
                $dbPaginator->perPage(),
                $dbPaginator->currentPage()
            );
        }

        // Manual pagination for in-memory collection
        $collection = collect($items);
        $total = $collection->count();
        $sliced = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($sliced, $total, $perPage, $page);
    }

    public function getFailedJobs(int $perPage = 20, int $page = 1, ?string $search = null): LengthAwarePaginator
    {
        if (! Schema::hasTable('failed_jobs')) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = DB::table('failed_jobs')
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('uuid', 'like', "%{$search}%")
                        ->orWhere('queue', 'like', "%{$search}%")
                        ->orWhere('payload', 'like', "%{$search}%")
                        ->orWhere('exception', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $transformed = $paginator->getCollection()->map(function ($row) {
            $payload = json_decode((string) $row->payload, true) ?: [];
            $displayName = $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown Job');

            return [
                'id' => (string) $row->id,
                'uuid' => (string) $row->uuid,
                'connection' => (string) $row->connection,
                'queue' => (string) $row->queue,
                'name' => Str::afterLast($displayName, '\\'),
                'full_name' => $displayName,
                'failed_at' => Carbon::parse($row->failed_at)->toIso8601String(),
                'exception_summary' => Str::limit($row->exception, 200),
                'exception_full' => $row->exception,
                'payload' => $payload,
            ];
        });

        return new LengthAwarePaginator(
            $transformed,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage()
        );
    }

    public function retryFailedJob(int|string $id): array
    {
        $exitCode = Artisan::call('queue:retry', ['id' => [(string) $id]]);
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'output' => $output,
        ];
    }

    public function retryAllFailedJobs(): array
    {
        $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'output' => $output,
        ];
    }

    public function forgetFailedJob(int|string $id): array
    {
        $exitCode = Artisan::call('queue:forget', ['id' => (string) $id]);
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'output' => $output,
        ];
    }

    public function flushFailedJobs(): array
    {
        $exitCode = Artisan::call('queue:flush');
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'output' => $output,
        ];
    }

    public function dispatchTestJob(string $message, int $delaySeconds = 0): array
    {
        if ($delaySeconds > 0) {
            TestQueueJob::dispatch($message)->delay(now()->addSeconds($delaySeconds));
            $note = "Dispatched with {$delaySeconds}s delay";
        } else {
            TestQueueJob::dispatch($message);
            $note = 'Dispatched immediately';
        }

        return [
            'success' => true,
            'note' => $note,
            'message' => $message,
            'queue' => config('queue.default'),
            'dispatched_at' => now()->toIso8601String(),
        ];
    }

    private function parseJobPayload(string $raw, string $status, string $queue, ?int $score = null): ?array
    {
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return null;
        }

        $displayName = $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Unknown Job');
        $createdAt = isset($payload['createdAt']) ? Carbon::createFromTimestamp((int) $payload['createdAt'])->toIso8601String() : null;
        $scheduledFor = $score ? Carbon::createFromTimestamp($score)->toIso8601String() : null;

        return [
            'id' => $payload['id'] ?? ($payload['uuid'] ?? Str::random(16)),
            'uuid' => $payload['uuid'] ?? '',
            'name' => Str::afterLast($displayName, '\\'),
            'full_name' => $displayName,
            'queue' => $queue,
            'status' => $status,
            'attempts' => (int) ($payload['attempts'] ?? 0),
            'max_tries' => $payload['maxTries'] ?? null,
            'timeout' => $payload['timeout'] ?? null,
            'created_at' => $createdAt,
            'scheduled_for' => $scheduledFor,
            'raw_payload' => $payload,
        ];
    }
}
