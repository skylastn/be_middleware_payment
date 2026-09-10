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
        if (! Schema::hasColumn('orders', 'name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('name')->nullable()->after('reference');
            });
        }

        // Backfill name from request payload if available
        DB::table('orders')
            ->whereNull('name')
            ->whereNotNull('request')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode($row->request ?? '', true);
                    if (! is_array($payload)) {
                        continue;
                    }

                    // Check nested structures (e.g. Midtrans customer_details, etc.)
                    $customerDetails = $payload['customer_details'] ?? [];
                    $firstName = $payload['firstName'] ?? $payload['first_name'] ?? $customerDetails['first_name'] ?? '';
                    $lastName = $payload['lastName'] ?? $payload['last_name'] ?? $customerDetails['last_name'] ?? '';

                    $name = trim("{$firstName} {$lastName}");
                    if (empty($name)) {
                        $name = $payload['customerVaName'] ?? $payload['viewName'] ?? $payload['name'] ?? null;
                    }

                    if (! empty($name)) {
                        DB::table('orders')->where('id', $row->id)->update(['name' => $name]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};
