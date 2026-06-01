<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string("id", 15);
            $table->string('type');
            $table->string('reference')->unique();
            $table->string('payment_method', 10)->nullable();
            $table->text('address')->nullable();
            $table->text('phone')->nullable();
            $table->string('status', 10)->nullable();
            $table->string('mode', 10);
            $table->text('email')->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->text('callback')->nullable();
            $table->text('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
