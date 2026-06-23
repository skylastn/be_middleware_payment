<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/login', [DashboardController::class, 'showLogin'])->name('login');
Route::post('/login', [DashboardController::class, 'storeLogin'])->name('login.store');
Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard.index');

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/{resource}', [DashboardController::class, 'index'])->name('resources.index');
        Route::get('/{resource}/create', [DashboardController::class, 'index'])->name('resources.create');
        Route::get('/{resource}/{id}', [DashboardController::class, 'index'])->name('resources.show');
        Route::get('/{resource}/{id}/edit', [DashboardController::class, 'index'])->name('resources.edit');
    });
