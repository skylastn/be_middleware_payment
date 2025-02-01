<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtherController;
use App\Http\Controllers\PaymentController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;

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

//order


Route::prefix('order')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/detail', [OrderController::class, 'detail']);
    Route::post('/create', [OrderController::class, 'store']);
});

Route::prefix('payment')->group(function () {
    Route::post('/createPayment', [OrderController::class, 'createPayment']);
    Route::get('/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);
    Route::get('/getPaymentMethod', [PaymentController::class, 'getPaymentMethod']);
    Route::get('/getDetailPaymentMethod', [PaymentController::class, 'getDetailPaymentMethod']);
});

Route::prefix('callback')->group(function () {
    Route::post('/duitku', [OrderController::class, 'callbackDuitku']);
    Route::post('/midtrans', [OrderController::class, 'callbackMidtrans']);
    Route::post('/xendit', [OrderController::class, 'callbackXendit']);
    Route::post('/spnpay', [PaymentController::class, 'callbackSPNPay']);
});

//project
Route::get('project', [ProjectController::class, 'index']);
Route::post('createProject', [ProjectController::class, 'store']);

//Other
Route::prefix('other')->group(function () {
    Route::post('/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
    Route::get('/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
