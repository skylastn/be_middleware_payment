<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gateway_reference', 128)->nullable();
            $table->string('gateway_bill_number', 128)->nullable();
            $table->unique(['payment_repository_id', 'gateway_bill_number'], 'orders_gateway_bill_number_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_gateway_bill_number_index');
            $table->dropColumn(['gateway_reference', 'gateway_bill_number']);
        });
    }
};
