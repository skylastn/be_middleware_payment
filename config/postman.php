<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Postman API Credentials & Sync Configuration
    |--------------------------------------------------------------------------
    |
    | API Key and Collection UID obtained from Postman Workspace integrations.
    |
    */
    'api_key' => env('POSTMAN_API_KEY', ''),
    'collection_uid' => env('POSTMAN_COLLECTION_UID', ''),
    'workspace_id' => env('POSTMAN_WORKSPACE_ID', ''),
    'collection_name' => env('POSTMAN_COLLECTION_NAME', 'Payment Middleware API'),
    'base_url' => env('APP_URL', 'http://localhost:8000'),
    'export_path' => storage_path('app/postman/collection.json'),
];
