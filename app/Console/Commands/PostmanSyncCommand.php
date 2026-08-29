<?php

namespace App\Console\Commands;

use App\Services\System\PostmanSyncService;
use Exception;
use Illuminate\Console\Command;

class PostmanSyncCommand extends Command
{
    protected $signature = 'postman:sync {--export : Export collection to local storage as well}';

    protected $description = 'Sync the API collection directly to Postman Cloud via Postman REST API';

    public function handle(PostmanSyncService $postmanService): int
    {
        $this->info('🚀 Generating Postman API collection...');

        if ($this->option('export')) {
            $exportedPath = $postmanService->exportToFile();
            $this->info("📁 Exported collection to: {$exportedPath}");
        }

        $this->info('☁️ Syncing collection with Postman Cloud API...');
        try {
            $result = $postmanService->syncToPostman();
            $collectionUid = $result['collection']['uid'] ?? $result['collection']['id'] ?? 'N/A';
            $this->info("✅ Successfully synced Postman collection! UID: {$collectionUid}");
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Failed to sync with Postman API: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
