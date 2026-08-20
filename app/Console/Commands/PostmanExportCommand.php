<?php

namespace App\Console\Commands;

use App\Services\System\PostmanSyncService;
use Illuminate\Console\Command;

class PostmanExportCommand extends Command
{
    protected $signature = 'postman:export {--path= : Target export file path}';

    protected $description = 'Export API collection to a local Postman Collection v2.1 JSON file';

    public function handle(PostmanSyncService $postmanService): int
    {
        $this->info('🚀 Generating Postman API collection...');

        $customPath = $this->option('path');
        $exportedPath = $postmanService->exportToFile($customPath);

        $this->info("✅ Postman collection exported successfully to: {$exportedPath}");
        return Command::SUCCESS;
    }
}
