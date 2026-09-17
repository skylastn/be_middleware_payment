<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'expired_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dateTime('expired_at')->nullable()->after('amount')->index('orders_expired_at_index');
            });
        }

        // Backfill existing orders: default to created_at + 1 hour
        try {
            DB::table('orders')
                ->whereNull('expired_at')
                ->whereNotNull('created_at')
                ->update([
                    'expired_at' => DB::raw('DATE_ADD(created_at, INTERVAL 1 HOUR)'),
                ]);
        } catch (Throwable) {
            // Fallback for database engines that do not support MySQL DATE_ADD
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'expired_at')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasIndex('orders', 'orders_expired_at_index')) {
                    $table->dropIndex('orders_expired_at_index');
                }
                $table->dropColumn('expired_at');
            });
        }
    }
};
