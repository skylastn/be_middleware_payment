<?php

use App\Enums\PaymentModeType;
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
        Schema::create('payment_repositories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_gateway_id');
            $table->enum('mode', PaymentModeType::values())->default(PaymentModeType::sandbox->value);
            $table->longText('value');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_repositories');
    }
};
