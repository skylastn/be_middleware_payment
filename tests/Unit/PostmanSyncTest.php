<?php

namespace Tests\Unit;

use App\Services\System\PostmanSyncService;
use Tests\TestCase;

class PostmanSyncTest extends TestCase
{
    public function test_generates_valid_postman_collection(): void
    {
        $service = new PostmanSyncService();
        $collection = $service->generateCollection();

        $this->assertIsArray($collection);
        $this->assertArrayHasKey('info', $collection);
        $this->assertArrayHasKey('item', $collection);
        $this->assertArrayHasKey('variable', $collection);
        $this->assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema']);
        $this->assertNotEmpty($collection['item']);
    }

    public function test_exports_collection_to_file(): void
    {
        $service = new PostmanSyncService();
        $tempPath = storage_path('app/postman/test_collection.json');

        $exported = $service->exportToFile($tempPath);

        $this->assertFileExists($exported);
        $content = json_decode(file_get_contents($exported), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('info', $content);

        @unlink($tempPath);
    }
}
