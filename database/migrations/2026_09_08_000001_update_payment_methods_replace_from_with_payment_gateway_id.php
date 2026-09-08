<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure default payment gateways exist
        if (Schema::hasTable('payment_gateways')) {
            $defaultGateways = [
                ['key' => 'duitku', 'name' => 'Duitku', 'description' => 'Duitku Payment Gateway'],
                ['key' => 'xendit', 'name' => 'Xendit', 'description' => 'Xendit Payment Gateway'],
                ['key' => 'midtrans', 'name' => 'Midtrans', 'description' => 'Midtrans Payment Gateway'],
                ['key' => 'spnpay', 'name' => 'SPNPay', 'description' => 'SPNPay Payment Gateway'],
                ['key' => 'stripe', 'name' => 'Stripe', 'description' => 'Stripe Payment Gateway'],
                ['key' => 'paprika', 'name' => 'Paprika', 'description' => 'Paprika Payment Gateway'],
            ];

            $now = now();
            foreach ($defaultGateways as $gw) {
                $exists = DB::table('payment_gateways')->where('key', $gw['key'])->first();
                if (! $exists) {
                    DB::table('payment_gateways')->insert([
                        'id' => (string) Str::uuid(),
                        'key' => $gw['key'],
                        'name' => $gw['name'],
                        'description' => $gw['description'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 2. Add payment_gateway_id to payment_methods if not exists
        if (! Schema::hasColumn('payment_methods', 'payment_gateway_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->uuid('payment_gateway_id')->nullable()->after('category_id');
                if (Schema::hasTable('payment_gateways')) {
                    $table->foreign('payment_gateway_id')->references('id')->on('payment_gateways')->nullOnDelete();
                }
            });
        }

        // 3. Add is_active to payment_methods if not exists
        if (! Schema::hasColumn('payment_methods', 'is_active')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('bankCode');
            });
        }

        // 4. Add composite index for client gateway and visibility queries
        if (Schema::hasColumn('payment_methods', 'payment_gateway_id') && Schema::hasColumn('payment_methods', 'is_active')) {
            $hasIndex = false;
            try {
                $indexes = collect(DB::select('SHOW INDEX FROM payment_methods'))->pluck('Key_name')->all();
                $hasIndex = in_array('pm_gateway_active_idx', $indexes, true);
            } catch (\Throwable) {
            }

            if (! $hasIndex) {
                Schema::table('payment_methods', function (Blueprint $table) {
                    $table->index(['payment_gateway_id', 'is_active'], 'pm_gateway_active_idx');
                });
            }
        }

        // 5. Migrate existing `from` data to `payment_gateway_id`
        if (Schema::hasColumn('payment_methods', 'from') && Schema::hasTable('payment_gateways')) {
            $gateways = DB::table('payment_gateways')->get()->keyBy('key');
            $methods = DB::table('payment_methods')->whereNull('payment_gateway_id')->whereNotNull('from')->get();
            foreach ($methods as $method) {
                $gw = $gateways->get($method->from);
                if ($gw) {
                    DB::table('payment_methods')
                        ->where('id', $method->id)
                        ->update(['payment_gateway_id' => $gw->id]);
                }
            }
        }

        // 6. Safely drop `from` column
        if (Schema::hasColumn('payment_methods', 'from')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('from');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add `from` column if missing
        if (! Schema::hasColumn('payment_methods', 'from')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('from')->nullable()->after('category_id');
            });
        }

        // 2. Restore `from` value from payment_gateways
        if (Schema::hasColumn('payment_methods', 'payment_gateway_id') && Schema::hasTable('payment_gateways')) {
            $gateways = DB::table('payment_gateways')->get()->keyBy('id');
            $methods = DB::table('payment_methods')->whereNotNull('payment_gateway_id')->get();
            foreach ($methods as $method) {
                $gw = $gateways->get($method->payment_gateway_id);
                if ($gw) {
                    DB::table('payment_methods')
                        ->where('id', $method->id)
                        ->update(['from' => $gw->key]);
                }
            }
        }

        // 3. Drop composite index
        if (Schema::hasColumn('payment_methods', 'payment_gateway_id') && Schema::hasColumn('payment_methods', 'is_active')) {
            $hasIndex = false;
            try {
                $indexes = collect(DB::select('SHOW INDEX FROM payment_methods'))->pluck('Key_name')->all();
                $hasIndex = in_array('pm_gateway_active_idx', $indexes, true);
            } catch (\Throwable) {
            }

            if ($hasIndex) {
                Schema::table('payment_methods', function (Blueprint $table) {
                    $table->dropIndex('pm_gateway_active_idx');
                });
            }
        }

        // 4. Drop is_active column
        if (Schema::hasColumn('payment_methods', 'is_active')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }

        // 5. Drop foreign key and column
        if (Schema::hasColumn('payment_methods', 'payment_gateway_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['payment_gateway_id']);
                }
                $table->dropColumn('payment_gateway_id');
            });
        }
    }
};
