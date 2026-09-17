<?php

namespace Tests\Feature;

use App\Interface\RabbitMQServiceInterface;
use App\Services\System\RabbitMQService;
use Tests\TestCase;

class RabbitMQServiceTest extends TestCase
{
    public function test_rabbitmq_service_is_bound_in_container(): void
    {
        $resolved = app(RabbitMQServiceInterface::class);
        $this->assertInstanceOf(RabbitMQService::class, $resolved);

        $concrete = app(RabbitMQService::class);
        $this->assertInstanceOf(RabbitMQService::class, $concrete);
        $this->assertSame($resolved, $concrete);
    }

    public function test_rabbitmq_service_returns_connection_details(): void
    {
        $service = app(RabbitMQServiceInterface::class);
        $details = $service->getConnectionDetails();

        $this->assertArrayHasKey('status', $details);
        $this->assertArrayHasKey('host', $details);
        $this->assertArrayHasKey('port', $details);
        $this->assertArrayHasKey('vhost', $details);
        $this->assertArrayHasKey('default_queue', $details);
        $this->assertArrayHasKey('exchange', $details);
        $this->assertArrayHasKey('exchange_type', $details);
    }

    public function test_rabbitmq_service_graceful_size_when_disconnected(): void
    {
        $service = app(RabbitMQServiceInterface::class);
        $size = $service->getQueueSize('non_existent_queue');

        // Should return 0 rather than throwing unhandled exception
        $this->assertIsInt($size);
        $this->assertGreaterThanOrEqual(0, $size);
    }
}
