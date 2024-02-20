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
Route::get('order', [OrderController::class, 'index']);
Route::post('createOrder', [OrderController::class, 'store']);
Route::post('Callback', [OrderController::class, 'Callback']);
Route::post('callbackMidtrans', [OrderController::class, 'callbackMidtrans']);
Route::post('callbackDuitku', [OrderController::class, 'callbackDuitku']);

//project
Route::get('project', [ProjectController::class, 'index']);
Route::post('createProject', [ProjectController::class, 'store']);

//payment
Route::get('payment/getPaymentCategory', [PaymentController::class, 'getPaymentCategory']);

//Other
Route::post('other/duitkuEncrpyt', [OtherController::class, 'duitkuEncrpyt']);
Route::get('other/duitkuPaymentSync', [OtherController::class, 'duitkuPaymentSync']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
