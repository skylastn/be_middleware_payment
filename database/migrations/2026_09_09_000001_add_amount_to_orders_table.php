<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('amount', 16, 2)->default(0)->after('payment_method');
            });
        }

        // Backfill amount for existing orders from request payload
        DB::table('orders')
            ->whereNotNull('request')
            ->where('amount', 0)
            ->cursor()
            ->each(function ($row) {
                $req = json_decode((string) $row->request, true);
                if (! is_array($req)) {
                    return;
                }

                $amount = $req['paymentAmount']
                    ?? $req['amount']
                    ?? ($req['transaction_details']['gross_amount'] ?? null)
                    ?? ($req['totalAmount']['value'] ?? null)
                    ?? ($req['line_items'][0]['price_data']['unit_amount'] ?? null);

                $amount = (float) ($amount ?? 0);
                if ($amount > 0) {
                    DB::table('orders')->where('id', $row->id)->update(['amount' => $amount]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('amount');
            });
        }
    }
};
