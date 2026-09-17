<?php

namespace App\Services\System;

use App\Interface\RabbitMQServiceInterface;
use Illuminate\Support\Facades\Queue;
use Throwable;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMQService implements RabbitMQServiceInterface
{
    private string $connectionName;

    public function __construct(string $connectionName = 'rabbitmq')
    {
        $this->connectionName = $connectionName;
    }

    public function isConnected(): bool
    {
        try {
            $defaultQueue = config("queue.connections.{$this->connectionName}.queue", 'default');
            $this->getQueueSize($defaultQueue);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getQueueSize(?string $queue = null): int
    {
        $queueName = $queue ?? config("queue.connections.{$this->connectionName}.queue", 'default');

        try {
            /** @var RabbitMQQueue $rabbitQueue */
            $rabbitQueue = Queue::connection($this->connectionName);

            return (int) $rabbitQueue->size($queueName);
        } catch (Throwable) {
            return 0;
        }
    }

    public function getConnectionDetails(): array
    {
        $defaultQueue = (string) config("queue.connections.{$this->connectionName}.queue", 'default');
        $host = (string) config("queue.connections.{$this->connectionName}.hosts.0.host", 'localhost');
        $port = (int) config("queue.connections.{$this->connectionName}.hosts.0.port", 5672);
        $vhost = (string) config("queue.connections.{$this->connectionName}.hosts.0.vhost", '/');
        $exchange = (string) config("queue.connections.{$this->connectionName}.exchange.name", 'default');
        $exchangeType = (string) config("queue.connections.{$this->connectionName}.exchange.type", 'direct');

        $status = 'disconnected';
        $error = null;

        try {
            $this->getQueueSize($defaultQueue);
            $status = 'connected';
        } catch (Throwable $e) {
            $status = 'disconnected';
            $error = $e->getMessage();
        }

        $details = [
            'status' => $status,
            'host' => $host,
            'port' => $port,
            'vhost' => $vhost,
            'default_queue' => $defaultQueue,
            'exchange' => $exchange,
            'exchange_type' => $exchangeType,
        ];

        if ($error !== null) {
            $details['error'] = $error;
        }

        return $details;
    }

    public function publishRaw(string $payload, ?string $queue = null, array $options = []): mixed
    {
        $queueName = $queue ?? config("queue.connections.{$this->connectionName}.queue", 'default');

        try {
            /** @var RabbitMQQueue $rabbitQueue */
            $rabbitQueue = Queue::connection($this->connectionName);

            return $rabbitQueue->pushRaw($payload, $queueName, $options);
        } catch (Throwable) {
            return null;
        }
    }

    public function purgeQueue(?string $queue = null): bool
    {
        $queueName = $queue ?? config("queue.connections.{$this->connectionName}.queue", 'default');

        try {
            /** @var RabbitMQQueue $rabbitQueue */
            $rabbitQueue = Queue::connection($this->connectionName);
            $rabbitQueue->purge($queueName);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
