<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TestController;
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

    // order
    Route::prefix('order')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/detail', [OrderController::class, 'detail']);
        Route::get('/checkOrderStatus', [OrderController::class, 'checkOrderStatus']);
        Route::post('/create', [OrderController::class, 'store']);
        Route::post('/stripe/confirm', [OrderController::class, 'confirmStripe']);
        Route::get('/{id}', [OrderController::class, 'show'])->middleware(['auth:sanctum', 'admin']);
        Route::post('/{id}/resend-callback', [OrderController::class, 'resendCallback'])->middleware(['auth:sanctum', 'admin']);
    });

    Route::prefix('payment')->group(function () {
        Route::post('/createPayment', [PaymentController::class, 'createPayment']);
        Route::get('/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);
        Route::get('/getPaymentMethod', [PaymentController::class, 'getPaymentMethod']);
        Route::get('/getDetailPaymentMethod', [PaymentController::class, 'getDetailPaymentMethod']);
        Route::get('/getPaymentGateway', [PaymentController::class, 'getPaymentGateway']);
        Route::get('/getPaymentRepository', [PaymentController::class, 'getPaymentRepository']);
        Route::get('/getSetting', [PaymentController::class, 'getSetting']);
        Route::get('/category/{id}', [PaymentController::class, 'showPaymentCategory']);
        Route::get('/method/{id}', [PaymentController::class, 'showPaymentMethod']);

        Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
            Route::post('/category/create', [PaymentController::class, 'createPaymentCategory']);
            Route::put('/category/{id}', [PaymentController::class, 'updatePaymentCategory']);
            Route::delete('/category/{id}', [PaymentController::class, 'deletePaymentCategory']);

            Route::post('/method/create', [PaymentController::class, 'createPaymentMethod']);
            Route::put('/method/{id}', [PaymentController::class, 'updatePaymentMethod']);
            Route::delete('/method/{id}', [PaymentController::class, 'deletePaymentMethod']);

            Route::get('/gateway/{id}', [PaymentController::class, 'showPaymentGateway']);
            Route::post('/gateway/create', [PaymentController::class, 'createPaymentGateway']);
            Route::put('/gateway/{id}', [PaymentController::class, 'updatePaymentGateway']);
            Route::delete('/gateway/{id}', [PaymentController::class, 'deletePaymentGateway']);

            Route::get('/repository/{id}', [PaymentController::class, 'showPaymentRepository']);
            Route::post('/repository/create', [PaymentController::class, 'createPaymentRepository']);
            Route::put('/repository/{id}', [PaymentController::class, 'updatePaymentRepository']);
            Route::delete('/repository/{id}', [PaymentController::class, 'deletePaymentRepository']);

            Route::get('/setting/{id}', [PaymentController::class, 'showSetting']);
            Route::post('/setting/create', [PaymentController::class, 'createSetting']);
            Route::put('/setting/{id}', [PaymentController::class, 'updateSetting']);
            Route::delete('/setting/{id}', [PaymentController::class, 'deleteSetting']);
        });
    });

    Route::prefix('callback')->group(function () {
        Route::post('/duitku', [CallbackController::class, 'callbackDuitku']);
        Route::post('/midtrans', [CallbackController::class, 'callbackMidtrans']);
        Route::post('/xendit', [CallbackController::class, 'callbackXendit']);
        Route::post('/spnpay', [CallbackController::class, 'callbackSPNPay']);
        Route::post('/stripe', [CallbackController::class, 'callbackStripe']);
    });

    // project
    Route::get('project', [ProjectController::class, 'index']);
    Route::post('project/sync-missing-log', [ProjectController::class, 'syncMissingLog'])->middleware(['auth:sanctum', 'admin']);
    Route::get('project/{id}', [ProjectController::class, 'show']);
    Route::post('project/create', [ProjectController::class, 'store']);
    Route::put('project/{id}', [ProjectController::class, 'update']);
    Route::delete('project/{id}', [ProjectController::class, 'delete'])->middleware(['auth:sanctum', 'admin']);

    // Other
    Route::prefix('other')->group(function () {
        Route::post('/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
        Route::get('/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);
    });

    // Test endpoints (dev / queue verification only)
    Route::prefix('test')->group(function () {
        Route::get('/queue', [TestController::class, 'testQueue']);
        Route::post('/queue', [TestController::class, 'testQueue']);
    });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    // Admin login API - must be at /api/login only (frontend calls /api/login).
    Route::middleware([\Illuminate\Cookie\Middleware\EncryptCookies::class, \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class])
        ->post('/login', [AdminAuthController::class, 'login']);

});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->name('admin.api.')
    ->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me'])->name('me');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    });
