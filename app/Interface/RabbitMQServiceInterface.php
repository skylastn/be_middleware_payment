<?php

namespace App\Interface;

interface RabbitMQServiceInterface
{
    /**
     * Check whether the RabbitMQ broker connection is reachable.
     */
    public function isConnected(): bool;

    /**
     * Get the count of pending messages waiting in the specified queue.
     */
    public function getQueueSize(?string $queue = null): int;

    /**
     * Retrieve connection configuration and status metadata.
     *
     * @return array{status: string, host: string, port: int, vhost: string, default_queue: string, exchange: string, exchange_type: string, error?: string}
     */
    public function getConnectionDetails(): array;

    /**
     * Publish a raw payload to RabbitMQ.
     */
    public function publishRaw(string $payload, ?string $queue = null, array $options = []): mixed;

    /**
     * Purge all messages from the specified queue.
     */
    public function purgeQueue(?string $queue = null): bool;
}
