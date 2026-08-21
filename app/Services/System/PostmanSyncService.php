<?php

namespace App\Services\System;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class PostmanSyncService
{
    public function generateCollection(): array
    {
        $baseUrl = config('postman.base_url', 'http://localhost:8000');
        $collectionName = config('postman.collection_name', 'Payment Middleware API');

        return [
            'info' => [
                'name' => $collectionName,
                '_postman_id' => config('postman.collection_uid', 'be-middleware-payment-v1'),
                'description' => 'Comprehensive Postman collection for Payment Middleware APIs, Merchant Gateways, Backoffice Management, and Webhooks.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $baseUrl,
                    'type' => 'string',
                ],
                [
                    'key' => 'admin_token',
                    'value' => '',
                    'type' => 'string',
                ],
                [
                    'key' => 'merchant_token',
                    'value' => '',
                    'type' => 'string',
                ],
                [
                    'key' => 'payment_app_key',
                    'value' => '',
                    'type' => 'string',
                ],
            ],
            'item' => [
                $this->buildAuthFolder(),
                $this->buildDashboardFolder(),
                $this->buildPaymentFolder(),
                $this->buildOrdersFolder(),
                $this->buildProjectsFolder(),
                $this->buildGatewaysFolder(),
                $this->buildRepositoriesFolder(),
                $this->buildMethodsFolder(),
                $this->buildCategoriesFolder(),
                $this->buildSettingsFolder(),
                $this->buildCallbacksFolder(),
                $this->buildPayoutsFolder(),
                $this->buildLogsFolder(),
            ],
        ];
    }

    public function exportToFile(?string $customPath = null): string
    {
        $collection = $this->generateCollection();
        $targetPath = $customPath ?: config('postman.export_path', storage_path('app/postman/collection.json'));

        $directory = dirname($targetPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($targetPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $targetPath;
    }

    public function syncToPostman(): array
    {
        $apiKey = config('postman.api_key');
        $collectionUid = config('postman.collection_uid');
        $workspaceId = config('postman.workspace_id');

        if (empty($apiKey)) {
            throw new Exception('POSTMAN_API_KEY is not configured in .env or config/postman.php');
        }

        $collection = $this->generateCollection();
        $payload = ['collection' => $collection];

        if (! empty($collectionUid)) {
            // Update existing collection
            $url = "https://api.getpostman.com/collections/{$collectionUid}";
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->put($url, $payload);
        } else {
            // Create new collection
            $url = 'https://api.getpostman.com/collections' . ($workspaceId ? "?workspace={$workspaceId}" : '');
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
        }

        if (! $response->successful()) {
            throw new Exception("Postman Sync failed with HTTP {$response->status()}: " . $response->body());
        }

        return $response->json();
    }

    private function buildAuthFolder(): array
    {
        return [
            'name' => '1. Auth & Admin',
            'item' => [
                [
                    'name' => 'Admin Login',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode(['email' => 'admin@example.com', 'password' => 'password'], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/login', 'host' => ['{{base_url}}'], 'path' => ['api', 'login']],
                    ],
                ],
                [
                    'name' => 'Get Authenticated Admin (Me)',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/admin/me', 'host' => ['{{base_url}}'], 'path' => ['api', 'admin', 'me']],
                    ],
                ],
                [
                    'name' => 'Admin Logout',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/admin/logout', 'host' => ['{{base_url}}'], 'path' => ['api', 'admin', 'logout']],
                    ],
                ],
            ],
        ];
    }

    private function buildDashboardFolder(): array
    {
        return [
            'name' => '2. Dashboard',
            'item' => [
                [
                    'name' => 'Get Dashboard Metrics & Statistics',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/admin/dashboard', 'host' => ['{{base_url}}'], 'path' => ['api', 'admin', 'dashboard']],
                    ],
                ],
            ],
        ];
    }

    private function buildPaymentFolder(): array
    {
        return [
            'name' => '3. Checkout & Payment Creation',
            'item' => [
                [
                    'name' => 'Create Payment Transaction',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Token', 'value' => '{{merchant_token}}'],
                            ['key' => 'PAYMENT_APP_KEY', 'value' => '{{payment_app_key}}'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'paymentAmount' => '50000',
                                'paymentMethod' => 'VC',
                                'merchantOrderId' => 'INV-' . time(),
                                'productDetails' => 'Order Item 1, Order Item 2',
                                'email' => 'customer@example.com',
                                'phoneNumber' => '08123456789',
                                'customerVaName' => 'Customer Name',
                                'callbackUrl' => 'https://merchant.example.com/callback',
                                'returnUrl' => 'https://merchant.example.com/return',
                                'expiryPeriod' => 60,
                            ], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/createPayment', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'createPayment']],
                    ],
                ],
                [
                    'name' => 'Get Payment Categories',
                    'request' => [
                        'method' => 'GET',
                        'header' => [['key' => 'Accept', 'value' => 'application/json']],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentCategory', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentCategory']],
                    ],
                ],
                [
                    'name' => 'Get Payment Methods',
                    'request' => [
                        'method' => 'GET',
                        'header' => [['key' => 'Accept', 'value' => 'application/json']],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentMethod', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentMethod']],
                    ],
                ],
            ],
        ];
    }

    private function buildOrdersFolder(): array
    {
        return [
            'name' => '4. Orders',
            'item' => [
                [
                    'name' => 'Create Order (Standard / Multi-Gateway)',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Token', 'value' => '{{merchant_token}}'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'paymentRepositoryId' => '{{paymentRepositoryId}}',
                                'merchantOrderId' => 'INV-' . time(),
                                'paymentAmount' => 50000,
                                'paymentMethod' => 'SP',
                                'productDetails' => 'Pembayaran Layanan',
                                'expiryPeriod' => 60,
                                'mode' => 'sandbox',
                                'currency' => 'idr',
                                'firstName' => 'John',
                                'lastName' => 'Doe',
                                'email' => 'customer@example.com',
                                'phoneNumber' => '08123456789',
                                'address' => 'Jakarta, Indonesia',
                                'customerVaName' => 'John Doe',
                                'callbackUrl' => 'https://merchant.example.com/callback',
                                'returnUrl' => 'https://merchant.example.com/return',
                            ], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order/create', 'host' => ['{{base_url}}'], 'path' => ['api', 'order', 'create']],
                    ],
                ],
                [
                    'name' => 'Create Order - Stripe (Checkout Session + Surcharge)',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Token', 'value' => '{{merchant_token}}'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'paymentRepositoryId' => '{{paymentRepositoryId}}',
                                'merchantOrderId' => 'INV-STRIPE-' . time(),
                                'paymentAmount' => 50000,
                                'productDetails' => 'Subscription Service',
                                'currency' => 'myr',
                                'mode' => 'sandbox',
                                'firstName' => 'John',
                                'lastName' => 'Doe',
                                'email' => 'customer@example.com',
                                'phoneNumber' => '08123456789',
                                'returnUrl' => 'https://merchant.example.com/return',
                                'stripe' => [
                                    'flow' => 'checkout_session',
                                    'surcharge_mode' => 'middleware_calc',
                                    'surcharge_percent' => 2.9,
                                    'surcharge_fixed' => 2000,
                                    'surcharge_label' => 'Processing Fee',
                                ],
                            ], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order/create', 'host' => ['{{base_url}}'], 'path' => ['api', 'order', 'create']],
                    ],
                ],
                [
                    'name' => 'Create Order - Stripe (Direct Flow / PaymentIntent)',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                            ['key' => 'Token', 'value' => '{{merchant_token}}'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'paymentRepositoryId' => '{{paymentRepositoryId}}',
                                'merchantOrderId' => 'INV-DIRECT-' . time(),
                                'paymentAmount' => 50000,
                                'productDetails' => 'Direct Card Payment',
                                'currency' => 'myr',
                                'mode' => 'sandbox',
                                'firstName' => 'John',
                                'lastName' => 'Doe',
                                'email' => 'customer@example.com',
                                'stripe' => [
                                    'flow' => 'direct',
                                    'token' => '{{stripe_card_token}}',
                                    'surcharge_mode' => 'middleware_calc',
                                    'surcharge_percent' => 2.9,
                                    'surcharge_fixed' => 2000,
                                    'surcharge_label' => 'Processing Fee',
                                ],
                            ], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order/create', 'host' => ['{{base_url}}'], 'path' => ['api', 'order', 'create']],
                    ],
                ],
                [
                    'name' => 'List Orders (Paginated & Filtered)',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order?page=1&per_page=15&search=&mode=all&status=all', 'host' => ['{{base_url}}'], 'path' => ['api', 'order']],
                    ],
                ],
                [
                    'name' => 'Get Order Detail',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order/1', 'host' => ['{{base_url}}'], 'path' => ['api', 'order', '1']],
                    ],
                ],
                [
                    'name' => 'Resend Order Callback',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/order/1/resend-callback', 'host' => ['{{base_url}}'], 'path' => ['api', 'order', '1', 'resend-callback']],
                    ],
                ],
            ],
        ];
    }

    private function buildProjectsFolder(): array
    {
        return [
            'name' => '5. Projects',
            'item' => [
                [
                    'name' => 'List Projects',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/project?page=1&per_page=15', 'host' => ['{{base_url}}'], 'path' => ['api', 'project']],
                    ],
                ],
                [
                    'name' => 'Create Project',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode([
                                'name' => 'E-Commerce Store',
                                'type' => 'STORE',
                                'slug' => 'duitku',
                                'callback' => 'https://store.example.com/callback',
                            ], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/project/create', 'host' => ['{{base_url}}'], 'path' => ['api', 'project', 'create']],
                    ],
                ],
                [
                    'name' => 'Sync Missing Log Tables',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/project/sync-missing-log', 'host' => ['{{base_url}}'], 'path' => ['api', 'project', 'sync-missing-log']],
                    ],
                ],
            ],
        ];
    }

    private function buildGatewaysFolder(): array
    {
        return [
            'name' => '6. Payment Gateways',
            'item' => [
                [
                    'name' => 'List Payment Gateways',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentGateway', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentGateway']],
                    ],
                ],
            ],
        ];
    }

    private function buildRepositoriesFolder(): array
    {
        return [
            'name' => '7. Payment Repositories',
            'item' => [
                [
                    'name' => 'List Payment Repositories',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentRepository', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentRepository']],
                    ],
                ],
            ],
        ];
    }

    private function buildMethodsFolder(): array
    {
        return [
            'name' => '8. Payment Methods Management',
            'item' => [
                [
                    'name' => 'List Payment Methods (Admin)',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentMethod', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentMethod']],
                    ],
                ],
            ],
        ];
    }

    private function buildCategoriesFolder(): array
    {
        return [
            'name' => '9. Payment Categories Management',
            'item' => [
                [
                    'name' => 'List Payment Categories (Admin)',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/getPaymentCategory', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getPaymentCategory']],
                    ],
                ],
            ],
        ];
    }

    private function buildSettingsFolder(): array
    {
        return [
            'name' => '10. Settings',
            'item' => [
                [
                    'name' => 'List Settings',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payment/getSetting', 'host' => ['{{base_url}}'], 'path' => ['api', 'payment', 'getSetting']],
                    ],
                ],
            ],
        ];
    }

    private function buildCallbacksFolder(): array
    {
        return [
            'name' => '11. Webhooks & Gateway Callbacks',
            'item' => [
                [
                    'name' => 'Duitku Callback',
                    'request' => [
                        'method' => 'POST',
                        'header' => [['key' => 'Content-Type', 'value' => 'application/json']],
                        'url' => ['raw' => '{{base_url}}/api/callback/duitku', 'host' => ['{{base_url}}'], 'path' => ['api', 'callback', 'duitku']],
                    ],
                ],
                [
                    'name' => 'Midtrans Callback',
                    'request' => [
                        'method' => 'POST',
                        'header' => [['key' => 'Content-Type', 'value' => 'application/json']],
                        'url' => ['raw' => '{{base_url}}/api/callback/midtrans', 'host' => ['{{base_url}}'], 'path' => ['api', 'callback', 'midtrans']],
                    ],
                ],
                [
                    'name' => 'Xendit Callback',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'x-callback-token', 'value' => '{{xendit_callback_token}}'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/callback/xendit', 'host' => ['{{base_url}}'], 'path' => ['api', 'callback', 'xendit']],
                    ],
                ],
                [
                    'name' => 'SPNPay Callback',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'On-Signature', 'value' => '{{spnpay_signature}}'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/callback/spnpay', 'host' => ['{{base_url}}'], 'path' => ['api', 'callback', 'spnpay']],
                    ],
                ],
                [
                    'name' => 'Stripe Webhook',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Stripe-Signature', 'value' => '{{stripe_signature}}'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/callback/stripe', 'host' => ['{{base_url}}'], 'path' => ['api', 'callback', 'stripe']],
                    ],
                ],
            ],
        ];
    }

    private function buildPayoutsFolder(): array
    {
        return [
            'name' => '12. Payouts',
            'item' => [
                [
                    'name' => 'Create Payout',
                    'request' => [
                        'method' => 'POST',
                        'header' => [
                            ['key' => 'Content-Type', 'value' => 'application/json'],
                            ['key' => 'Token', 'value' => '{{merchant_token}}'],
                        ],
                        'body' => [
                            'mode' => 'raw',
                            'raw' => json_encode(['amount' => 10000, 'recipient' => 'acct_123'], JSON_PRETTY_PRINT),
                        ],
                        'url' => ['raw' => '{{base_url}}/api/payout/create', 'host' => ['{{base_url}}'], 'path' => ['api', 'payout', 'create']],
                    ],
                ],
            ],
        ];
    }

    private function buildLogsFolder(): array
    {
        return [
            'name' => '13. Logs',
            'item' => [
                [
                    'name' => 'List Log Files',
                    'request' => [
                        'method' => 'GET',
                        'header' => [
                            ['key' => 'Authorization', 'value' => 'Bearer {{admin_token}}'],
                            ['key' => 'Accept', 'value' => 'application/json'],
                        ],
                        'url' => ['raw' => '{{base_url}}/api/admin/logs', 'host' => ['{{base_url}}'], 'path' => ['api', 'admin', 'logs']],
                    ],
                ],
            ],
        ];
    }
}
