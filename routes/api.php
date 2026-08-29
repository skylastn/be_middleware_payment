<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminGatewayHistoryController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Unified API routes for Client Payment, Merchant Server-to-Server,
| Payment Webhooks, and Admin Backoffice Management.
|
*/

Route::middleware('throttle:api')->group(function () {

    // ---------------------------------------------------------------------
    // 1. Client Payment Checkout Flow (Protected by Expirable Redis Token)
    // ---------------------------------------------------------------------
    Route::prefix('client')->middleware('client.token.auth')->controller(ClientPaymentController::class)->group(function () {
        Route::prefix('order')->group(function () {
            Route::get('/detail', 'detail');
            Route::get('/checkOrderStatus', 'checkOrderStatus');
            Route::post('/createPayment', 'createPayment');
        });

        Route::prefix('payment')->group(function () {
            Route::get('/getPaymentCategory', 'getPaymentCategory');
            Route::get('/getPaymentMethod', 'getPaymentMethod');
            Route::get('/getDetailPaymentMethod', 'getDetailPaymentMethod');
        });
    });

    // ---------------------------------------------------------------------
    // 2. Merchant Server-to-Server API (Protected by Project Token)
    // ---------------------------------------------------------------------
    Route::prefix('order')->middleware('project.auth')->controller(OrderController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/detail', 'detail');
        Route::get('/checkOrderStatus', 'checkOrderStatus');
        Route::post('/create', 'store');
        Route::post('/set-success', 'setSuccessMerchant');
        Route::post('/stripe/confirm', 'confirmStripe');
    });

    Route::prefix('payment')->controller(PaymentController::class)->group(function () {
        Route::post('/createPayment', 'createPayment')->middleware('project.auth');
        Route::get('/getPaymentCategory', 'getPaymentCategory');
        Route::get('/getPaymentMethod', 'getPaymentMethod');
        Route::get('/getDetailPaymentMethod', 'getDetailPaymentMethod');
        Route::get('/category/{id}', 'showPaymentCategory');
        Route::get('/method/{id}', 'showPaymentMethod');
    });

    Route::prefix('payout')->controller(PayoutController::class)->group(function () {
        Route::post('/create', 'create')->middleware('project.auth');
    });

    // ---------------------------------------------------------------------
    // 3. Webhooks & Callbacks
    // ---------------------------------------------------------------------
    Route::prefix('callback')->controller(CallbackController::class)->group(function () {
        Route::post('/duitku', 'callbackDuitku');
        Route::post('/midtrans', 'callbackMidtrans');
        Route::post('/xendit', 'callbackXendit');
        Route::post('/spnpay', 'callbackSPNPay');
        Route::post('/stripe', 'callbackStripe');
        Route::post('/paprika', 'callbackPaprika');
        Route::post('/payout/stripe', 'callbackPayoutStripe');
    });

    Route::prefix('webhook')->controller(WebhookController::class)->group(function () {
        Route::post('/paprika', 'webhookPaprika');
    });

    // ---------------------------------------------------------------------
    // 4. Utility & Test Endpoints (Restricted to Admin)
    // ---------------------------------------------------------------------
    Route::prefix('other')->middleware(['auth:sanctum', 'admin'])->controller(OtherController::class)->group(function () {
        Route::post('/duitkuEncrpyt', 'duitkuEncrpyt');
        Route::get('/duitkuPaymentSync', 'duitkuPaymentSync');
    });

    Route::prefix('test')->middleware(['auth:sanctum', 'admin'])->controller(TestController::class)->group(function () {
        Route::get('/queue', 'testQueue');
        Route::post('/queue', 'testQueue');
    });

    Route::middleware('auth:sanctum')->get('/user', fn(Request $request) => $request->user());

    // Admin login API (Rate-limited to 10 attempts / min)
    Route::middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, 'throttle:10,1'])
        ->post('/login', [AdminAuthController::class, 'login']);

    // ---------------------------------------------------------------------
    // 5. Admin Backoffice API (Protected by Sanctum Token + Admin Role)
    // ---------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.api.')->group(function (): void {
        Route::controller(AdminAuthController::class)->group(function () {
            Route::get('/me', 'me')->name('me');
            Route::post('/logout', 'logout')->name('logout');
        });

        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/gateway-history', AdminGatewayHistoryController::class)->name('gateway-history');

        // Admin Order Management
        Route::prefix('orders')->controller(OrderController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::post('/{id}/resend-callback', 'resendCallback');
            Route::post('/{id}/set-success', 'setSuccessAdmin');
        });

        // Admin Projects Management
        Route::prefix('projects')->controller(ProjectController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/{id}', 'show');
            Route::post('/create', 'store');
            Route::put('/{id}', 'update');
            Route::delete('/{id}', 'delete');
            Route::post('/sync-missing-log', 'syncMissingLog');
        });

        // Admin Payment Resources
        Route::controller(PaymentController::class)->group(function () {
            Route::prefix('payment-gateways')->group(function () {
                Route::get('/', 'getPaymentGateway');
                Route::get('/{id}', 'showPaymentGateway');
                Route::post('/create', 'createPaymentGateway');
                Route::put('/{id}', 'updatePaymentGateway');
                Route::delete('/{id}', 'deletePaymentGateway');
            });

            Route::prefix('payment-repositories')->group(function () {
                Route::get('/', 'getPaymentRepository');
                Route::get('/{id}', 'showPaymentRepository');
                Route::post('/create', 'createPaymentRepository');
                Route::put('/{id}', 'updatePaymentRepository');
                Route::delete('/{id}', 'deletePaymentRepository');
                Route::post('/{id}/test-order', 'testOrder');
            });

            Route::prefix('payment-methods')->group(function () {
                Route::get('/', 'getPaymentMethod');
                Route::get('/{id}', 'showPaymentMethod');
                Route::post('/create', 'createPaymentMethod');
                Route::put('/{id}', 'updatePaymentMethod');
                Route::delete('/{id}', 'deletePaymentMethod');
            });

            Route::prefix('payment-categories')->group(function () {
                Route::get('/', 'getPaymentCategory');
                Route::get('/{id}', 'showPaymentCategory');
                Route::post('/create', 'createPaymentCategory');
                Route::put('/{id}', 'updatePaymentCategory');
                Route::delete('/{id}', 'deletePaymentCategory');
            });

            Route::prefix('settings')->group(function () {
                Route::get('/', 'getSetting');
                Route::get('/{id}', 'showSetting');
                Route::post('/create', 'createSetting');
                Route::put('/{id}', 'updateSetting');
                Route::delete('/{id}', 'deleteSetting');
            });
        });
    });
});
