<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\AdminResourceController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('dashboard.index');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::post('/orders/{id}/resend-callback', [AdminResourceController::class, 'resendOrderCallback'])->name('orders.resend-callback');
        Route::get('/{resource}', [AdminResourceController::class, 'index'])->name('resources.index');
        Route::get('/{resource}/create', [AdminResourceController::class, 'create'])->name('resources.create');
        Route::post('/{resource}', [AdminResourceController::class, 'store'])->name('resources.store');
        Route::get('/{resource}/{id}', [AdminResourceController::class, 'show'])->name('resources.show');
        Route::get('/{resource}/{id}/edit', [AdminResourceController::class, 'edit'])->name('resources.edit');
        Route::put('/{resource}/{id}', [AdminResourceController::class, 'update'])->name('resources.update');
        Route::delete('/{resource}/{id}', [AdminResourceController::class, 'destroy'])->name('resources.destroy');
    });
