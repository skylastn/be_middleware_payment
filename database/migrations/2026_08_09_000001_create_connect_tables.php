<?php

use App\Enums\PayoutGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $gateways = implode(',', array_map(
            fn (string $v): string => DB::getPdo()->quote($v),
            PayoutGateway::values(),
        ));

        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('myr');
            $table->string('status')->default('pending');
            $table->string('reference')->unique();
            $table->string('internal_id')->unique()->nullable();
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->text('callback')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('reference');
        });

        DB::statement("ALTER TABLE payouts ADD COLUMN gateway ENUM({$gateways}) NOT NULL AFTER currency");
        DB::statement('ALTER TABLE payouts ADD INDEX payouts_gateway_index (gateway)');

        Schema::create('payout_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payout_id')->constrained('payouts')->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('status', 50);
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at')->useCurrent();

            $table->index('payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_histories');
        Schema::dropIfExists('payouts');
    }
};
