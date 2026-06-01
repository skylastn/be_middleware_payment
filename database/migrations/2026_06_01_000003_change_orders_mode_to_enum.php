<?php

use App\Enums\PaymentModeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'mode')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE orders MODIFY mode ENUM(%s) NOT NULL',
            $this->quotedEnumValues(),
        ));
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'mode')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE orders MODIFY mode VARCHAR(10) NOT NULL');
    }

    private function quotedEnumValues(): string
    {
        return implode(
            ', ',
            array_map(
                fn (string $mode): string => DB::getPdo()->quote($mode),
                PaymentModeType::values(),
            ),
        );
    }
};
