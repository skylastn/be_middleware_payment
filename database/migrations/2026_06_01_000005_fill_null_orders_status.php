<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        DB::table('orders')
            ->whereNull('status')
            ->update(['status' => OrderStatus::PENDING->value]);
    }

    public function down(): void
    {
        //
    }
};
