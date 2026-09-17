<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasIndex('orders', 'orders_status_index')) {
                $table->index('status', 'orders_status_index');
            }
            if (! Schema::hasIndex('orders', 'orders_type_index')) {
                $table->index('type', 'orders_type_index');
            }
            if (! Schema::hasIndex('orders', 'orders_created_at_index')) {
                $table->index('created_at', 'orders_created_at_index');
            }
            if (Schema::hasColumn('orders', 'payment_repository_id') && ! Schema::hasIndex('orders', 'orders_payment_repository_id_index')) {
                $table->index('payment_repository_id', 'orders_payment_repository_id_index');
            }
            if (! Schema::hasIndex('orders', 'orders_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            }
            if (! Schema::hasIndex('orders', 'orders_type_created_at_index')) {
                $table->index(['type', 'created_at'], 'orders_type_created_at_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', 'orders_status_index')) {
                $table->dropIndex('orders_status_index');
            }
            if (Schema::hasIndex('orders', 'orders_type_index')) {
                $table->dropIndex('orders_type_index');
            }
            if (Schema::hasIndex('orders', 'orders_created_at_index')) {
                $table->dropIndex('orders_created_at_index');
            }
            if (Schema::hasIndex('orders', 'orders_payment_repository_id_index')) {
                $table->dropIndex('orders_payment_repository_id_index');
            }
            if (Schema::hasIndex('orders', 'orders_status_created_at_index')) {
                $table->dropIndex('orders_status_created_at_index');
            }
            if (Schema::hasIndex('orders', 'orders_type_created_at_index')) {
                $table->dropIndex('orders_type_created_at_index');
            }
        });
    }
};
