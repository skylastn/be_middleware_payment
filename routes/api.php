<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminGatewayHistoryController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\PaprikaController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TestController;
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
        Route::post('/createPayment', [PaymentController::class, 'createPayment'])->middleware('project.auth');
        Route::get('/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);
        Route::get('/getPaymentMethod', [PaymentController::class, 'getPaymentMethod']);
        Route::get('/getDetailPaymentMethod', [PaymentController::class, 'getDetailPaymentMethod']);
        Route::get('/category/{id}', [PaymentController::class, 'showPaymentCategory']);
        Route::get('/method/{id}', [PaymentController::class, 'showPaymentMethod']);
    });

    Route::prefix('payout')->controller(PayoutController::class)->group(function () {
        Route::post('/create', [PayoutController::class, 'create'])->middleware('project.auth');
    });

    // ---------------------------------------------------------------------
    // 3. Webhooks & Callbacks
    // ---------------------------------------------------------------------
    Route::prefix('callback')->controller(CallbackController::class)->group(function () {
        Route::post('/duitku', [CallbackController::class, 'callbackDuitku']);
        Route::post('/midtrans', [CallbackController::class, 'callbackMidtrans']);
        Route::post('/xendit', [CallbackController::class, 'callbackXendit']);
        Route::post('/spnpay', [CallbackController::class, 'callbackSPNPay']);
        Route::post('/stripe', [CallbackController::class, 'callbackStripe']);
        Route::post('/paprika', [CallbackController::class, 'callbackPaprika']);
        Route::post('/payout/stripe', [CallbackController::class, 'callbackPayoutStripe']);
    });

    // Route::prefix('webhook')->controller(WebhookController::class)->group(function () {
    //     Route::post('/paprika', [WebhookController::class, 'webhookPaprika']);
    // });

    // Paprika Dedicated Endpoints
    Route::prefix('paprika')->controller(PaprikaController::class)->group(function () {
        Route::post('/snap/v1.0/access-token/b2b', [PaprikaController::class, 'snapAccessTokenB2B']);
        Route::post('/webhook', [PaprikaController::class, 'webhook']);
        Route::post('/callback', [PaprikaController::class, 'callback']);
    });

    // ---------------------------------------------------------------------
    // 4. Utility & Test Endpoints (Restricted to Admin)
    // ---------------------------------------------------------------------
    Route::prefix('other')->middleware(['auth:sanctum', 'admin'])->controller(OtherController::class)->group(function () {
        Route::post('/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
        Route::get('/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);
    });

    Route::prefix('test')->middleware(['auth:sanctum', 'admin'])->controller(TestController::class)->group(function () {
        Route::get('/queue', [TestController::class, 'testQueue']);
        Route::post('/queue', [TestController::class, 'testQueue']);
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
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        });

        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/gateway-history', AdminGatewayHistoryController::class)->name('gateway-history');

        // Admin Order Management
        Route::prefix('orders')->controller(OrderController::class)->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::post('/{id}/resend-callback', [OrderController::class, 'resendCallback']);
            Route::post('/{id}/set-success', [OrderController::class, 'setSuccessAdmin']);
        });

        // Admin Projects Management
        Route::prefix('projects')->controller(ProjectController::class)->group(function () {
            Route::get('/', [ProjectController::class, 'index']);
            Route::get('/{id}', [ProjectController::class, 'show']);
            Route::get('/{id}/logs', [ProjectController::class, 'logs']);
            Route::get('/{id}/log-keys', [ProjectController::class, 'logKeys']);
            Route::delete('/{id}/logs', [ProjectController::class, 'clearLogs']);
            Route::post('/create', [ProjectController::class, 'store']);
            Route::put('/{id}', [ProjectController::class, 'update']);
            Route::delete('/{id}', [ProjectController::class, 'delete']);
            Route::post('/sync-missing-log', [ProjectController::class, 'syncMissingLog']);
        });

        // Admin Payment Resources
        Route::controller(PaymentController::class)->group(function () {
            Route::prefix('payment-gateways')->group(function () {
                Route::get('/', [PaymentController::class, 'getPaymentGateway']);
                Route::get('/{id}', [PaymentController::class, 'showPaymentGateway']);
                Route::post('/create', [PaymentController::class, 'createPaymentGateway']);
                Route::put('/{id}', [PaymentController::class, 'updatePaymentGateway']);
                Route::delete('/{id}', [PaymentController::class, 'deletePaymentGateway']);
            });

            Route::prefix('payment-repositories')->group(function () {
                Route::get('/', [PaymentController::class, 'getPaymentRepository']);
                Route::get('/{id}', [PaymentController::class, 'showPaymentRepository']);
                Route::post('/create', [PaymentController::class, 'createPaymentRepository']);
                Route::put('/{id}', [PaymentController::class, 'updatePaymentRepository']);
                Route::delete('/{id}', [PaymentController::class, 'deletePaymentRepository']);
                Route::post('/{id}/test-order', [PaymentController::class, 'testOrder']);
            });

            Route::prefix('payment-methods')->group(function () {
                Route::get('/', [PaymentController::class, 'getPaymentMethod']);
                Route::get('/{id}', [PaymentController::class, 'showPaymentMethod']);
                Route::post('/create', [PaymentController::class, 'createPaymentMethod']);
                Route::put('/{id}', [PaymentController::class, 'updatePaymentMethod']);
                Route::delete('/{id}', [PaymentController::class, 'deletePaymentMethod']);
            });

            Route::prefix('payment-categories')->group(function () {
                Route::get('/', [PaymentController::class, 'getPaymentCategory']);
                Route::get('/{id}', [PaymentController::class, 'showPaymentCategory']);
                Route::post('/create', [PaymentController::class, 'createPaymentCategory']);
                Route::put('/{id}', [PaymentController::class, 'updatePaymentCategory']);
                Route::delete('/{id}', [PaymentController::class, 'deletePaymentCategory']);
            });

            Route::prefix('settings')->group(function () {
                Route::get('/', [PaymentController::class, 'getSetting']);
                Route::get('/{id}', [PaymentController::class, 'showSetting']);
                Route::post('/create', [PaymentController::class, 'createSetting']);
                Route::put('/{id}', [PaymentController::class, 'updateSetting']);
                Route::delete('/{id}', [PaymentController::class, 'deleteSetting']);
            });
        });
    });
});
