<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Schedule::call(function () {
    (new \App\Services\Payment\CallbackDeliveryService)->recover();
})->name('recover-payment-callbacks')->everyMinute()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::call(function () {
    $orders = \App\Model\Entity\Order::without(['payment_methods', 'project', 'payment_repository'])
        ->where('status', 'PENDING')->where('created_at', '>=', now()->subDay())
        ->where('created_at', '<=', now()->subMinutes(2))
        ->where('reconciliation_attempts', '<', 3)
        ->where(fn ($q) => $q->where('expires_at', '<=', now()->subMinutes(2))
            ->orWhere(fn ($q) => $q->whereIn('invoice_state', ['PROCESSING', 'UNKNOWN'])->where('reconciliation_attempts', 0)))
        ->whereHas('project', fn ($q) => $q->where('slug', 'duitku'))
        ->where(fn ($q) => $q->whereNull('reconciled_at')->orWhere('reconciled_at', '<=', now()->subMinutes(30)))
        ->orderBy('reconciled_at')->limit(5)->get();
    foreach ($orders as $order) {
        $order->setAttribute('reconciled_at', now());
        $order->setAttribute('reconciliation_attempts', ((int) $order->getAttribute('reconciliation_attempts')) + 1);
        $order->save();
        \App\Jobs\ReconcileDuitkuPayment::dispatch($order->getId())->onQueue('payment-reconcile');
    }
})->name('reconcile-duitku-payments')->everyMinute()->withoutOverlapping();
