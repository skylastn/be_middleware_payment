<?php

use App\Http\Controllers\Api\CallbackController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Model\Entity\Project;
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
    });

    Route::prefix('payment')->group(function () {
        Route::post('/createPayment', [PaymentController::class, 'createPayment']);
        Route::get('/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);
        Route::get('/getPaymentMethod', [PaymentController::class, 'getPaymentMethod']);
        Route::get('/getDetailPaymentMethod', [PaymentController::class, 'getDetailPaymentMethod']);
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
    Route::get('project/{id}', [ProjectController::class, 'show']);
    Route::post('project/create', [ProjectController::class, 'store']);
    Route::put('project/{id}', [ProjectController::class, 'update']);

    // Other
    Route::prefix('other')->group(function () {
        Route::post('/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
        Route::get('/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);
    });

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

});
