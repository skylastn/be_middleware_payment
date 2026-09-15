<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_state', 20)->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedSmallInteger('reconciliation_attempts')->default(0);
        });
        Schema::create('merchant_callback_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('deduplication_key', 64)->unique();
            $table->string('project_id', 36);
            $table->string('reference');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_callback_deliveries');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_state', 'expires_at', 'reconciled_at', 'reconciliation_attempts']);
        });
    }
};
