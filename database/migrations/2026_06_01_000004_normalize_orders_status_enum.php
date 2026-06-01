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

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE orders MODIFY status ENUM('PENDING', 'PAID', 'FAILED', 'Expired', 'SUCCESS', 'CAPTURE', 'DENY', 'EXPIRE', 'CANCEL', 'SETTLED', 'CANCELED', 'REFUNDED') NULL",
        );

        DB::table('orders')->where('status', 'PAID')->update(['status' => OrderStatus::SUCCESS->value]);
        DB::table('orders')->where('status', 'Expired')->update(['status' => OrderStatus::EXPIRED->value]);

        DB::statement(sprintf(
            'ALTER TABLE orders MODIFY status ENUM(%s) NULL',
            $this->quotedEnumValues(),
        ));
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE orders MODIFY status ENUM('PENDING', 'PAID', 'FAILED', 'Expired', 'SUCCESS', 'CAPTURE', 'DENY', 'EXPIRE', 'CANCEL', 'SETTLED', 'CANCELED', 'REFUNDED') NULL",
        );
    }

    private function quotedEnumValues(): string
    {
        return implode(
            ', ',
            array_map(
                fn (string $status): string => DB::getPdo()->quote($status),
                OrderStatus::values(),
            ),
        );
    }
};
