<?php

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

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE orders MODIFY status ENUM('', 'PENDING', 'PAID', 'FAILED', 'Expired', 'SUCCESS', 'CAPTURE', 'DENY', 'EXPIRE', 'CANCEL', 'SETTLED', 'CANCELED', 'REFUNDED') NULL",
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE orders MODIFY status VARCHAR(10) NULL');
    }

};
