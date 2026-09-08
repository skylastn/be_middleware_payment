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
        if (! Schema::hasColumn('orders', 'return_url')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('return_url')->nullable()->after('url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'return_url')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('return_url');
            });
        }
    }
};
