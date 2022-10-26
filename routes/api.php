<?php

use App\Http\Controllers\OrderController;
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

//project
Route::get('project', [ProjectController::class, 'index']);
Route::post('createProject', [ProjectController::class, 'store']);


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
