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
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('throttle:api')->group(function () {

    // client (frontend payment page - protected by expirable Redis token)
    Route::prefix('client')->middleware('client.token.auth')->group(function () {
        Route::prefix('order')->group(function () {
            Route::get('/detail', [ClientPaymentController::class, 'detail']);
            Route::get('/checkOrderStatus', [ClientPaymentController::class, 'checkOrderStatus']);
            Route::post('/createPayment', [ClientPaymentController::class, 'createPayment']);
        });

        Route::prefix('payment')->group(function () {
            Route::get('/getPaymentCategory', [ClientPaymentController::class, 'getPaymentCategory']);
            Route::get('/getPaymentMethod', [ClientPaymentController::class, 'getPaymentMethod']);
            Route::get('/getDetailPaymentMethod', [ClientPaymentController::class, 'getDetailPaymentMethod']);
        });
    });

    // merchant server-to-server order API (protected by static project Token)
    Route::prefix('order')->middleware('project.auth')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/detail', [OrderController::class, 'detail']);
        Route::get('/checkOrderStatus', [OrderController::class, 'checkOrderStatus']);
        Route::post('/create', [OrderController::class, 'store']);
        Route::post('/stripe/confirm', [OrderController::class, 'confirmStripe']);
    });

    // merchant payment & public discovery API
    Route::prefix('payment')->group(function () {
        Route::post('/createPayment', [PaymentController::class, 'createPayment'])->middleware('project.auth');
        Route::get('/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);
        Route::get('/getPaymentMethod', [PaymentController::class, 'getPaymentMethod']);
        Route::get('/getDetailPaymentMethod', [PaymentController::class, 'getDetailPaymentMethod']);
        Route::get('/category/{id}', [PaymentController::class, 'showPaymentCategory']);
        Route::get('/method/{id}', [PaymentController::class, 'showPaymentMethod']);
    });

    Route::prefix('callback')->group(function () {
        Route::post('/duitku', [CallbackController::class, 'callbackDuitku']);
        Route::post('/midtrans', [CallbackController::class, 'callbackMidtrans']);
        Route::post('/xendit', [CallbackController::class, 'callbackXendit']);
        Route::post('/spnpay', [CallbackController::class, 'callbackSPNPay']);
        Route::post('/stripe', [CallbackController::class, 'callbackStripe']);
        Route::post('/paprika', [CallbackController::class, 'callbackPaprika']);

        Route::prefix('payout')->group(function () {
            Route::post('/stripe', [CallbackController::class, 'callbackPayoutStripe']);
        });
    });

    Route::prefix('payout')->group(function () {
        Route::post('/create', [PayoutController::class, 'create'])->middleware('project.auth');
    });

    Route::prefix('webhook')->group(function () {
        Route::post('/paprika', [WebhookController::class, 'webhookPaprika']);
    });

    // Other
    Route::prefix('other')->group(function () {
        Route::post('/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
        Route::get('/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);
    });

    // Test endpoints (dev / queue verification only - restricted to admin)
    Route::prefix('test')->middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/queue', [TestController::class, 'testQueue']);
        Route::post('/queue', [TestController::class, 'testQueue']);
    });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin login API - must be at /api/login only with strict rate-limiting (10 attempts / min)
    Route::middleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, 'throttle:10,1'])
        ->post('/login', [AdminAuthController::class, 'login']);

    // Admin Backoffice API routes (strictly protected by Sanctum token + Admin role)
    Route::middleware(['auth:sanctum', 'admin'])
        ->prefix('admin')
        ->name('admin.api.')
        ->group(function (): void {
            Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
            Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('/gateway-history', AdminGatewayHistoryController::class)->name('gateway-history');

            // Admin Order Management
            Route::prefix('orders')->group(function () {
                Route::get('/', [OrderController::class, 'index']);
                Route::get('/{id}', [OrderController::class, 'show']);
                Route::post('/{id}/resend-callback', [OrderController::class, 'resendCallback']);
            });

            // Admin Projects Management
            Route::prefix('projects')->group(function () {
                Route::get('/', [ProjectController::class, 'index']);
                Route::get('/{id}', [ProjectController::class, 'show']);
                Route::post('/create', [ProjectController::class, 'store']);
                Route::put('/{id}', [ProjectController::class, 'update']);
                Route::delete('/{id}', [ProjectController::class, 'delete']);
                Route::post('/sync-missing-log', [ProjectController::class, 'syncMissingLog']);
            });

            // Admin Payment Resources
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
