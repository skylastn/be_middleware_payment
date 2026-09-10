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
        // 1. Ensure category_id column exists
        if (! Schema::hasColumn('payment_methods', 'category_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->unsignedInteger('category_id')->nullable()->after('key');
            });
        }

        // 2. Ensure default categories exist and are normalized in payment_categories
        if (Schema::hasTable('payment_categories')) {
            // Normalize legacy keys
            DB::table('payment_categories')
                ->whereIn('key', ['virtual-account', 'virtual_account'])
                ->update(['key' => 'va']);

            $defaultCategories = [
                [
                    'key' => 'va',
                    'title' => 'Virtual Account',
                    'detail' => 'Pembayaran melalui transfer Virtual Account Bank',
                ],
                [
                    'key' => 'cc',
                    'title' => 'Credit Card',
                    'detail' => 'Pembayaran instan menggunakan kartu kredit atau debit',
                ],
                [
                    'key' => 'qris',
                    'title' => 'QRIS',
                    'detail' => 'Pembayaran digital melalui scan QRIS (GoPay, OVO, Dana, ShopeePay, LinkAja, dll)',
                ],
                [
                    'key' => 'ewallet',
                    'title' => 'E-Wallet',
                    'detail' => 'Pembayaran melalui dompet digital (OVO, DANA, ShopeePay, LinkAja)',
                ],
                [
                    'key' => 'retail',
                    'title' => 'Retail / Convenience Store',
                    'detail' => 'Pembayaran melalui gerai retail (Indomaret, Alfamart)',
                ],
            ];

            $now = now();
            foreach ($defaultCategories as $cat) {
                $exists = DB::table('payment_categories')->where('key', $cat['key'])->first();
                if (! $exists) {
                    DB::table('payment_categories')->insert([
                        'key' => $cat['key'],
                        'title' => $cat['title'],
                        'detail' => $cat['detail'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Retrieve category IDs
            $catVa = DB::table('payment_categories')->where('key', 'va')->value('id');
            $catQris = DB::table('payment_categories')->where('key', 'qris')->value('id');
            $catCc = DB::table('payment_categories')->where('key', 'cc')->value('id');
            $catEwallet = DB::table('payment_categories')->where('key', 'ewallet')->value('id');
            $catRetail = DB::table('payment_categories')->where('key', 'retail')->value('id');

            // 3. Migrate existing payment methods data to category_id
            $hasType = Schema::hasColumn('payment_methods', 'type');

            // 3a. QRIS
            if ($catQris) {
                DB::table('payment_methods')
                    ->whereNull('category_id')
                    ->where(function ($query) use ($hasType) {
                        if ($hasType) {
                            $query->whereIn('type', ['qris', 'QRIS'])
                                ->orWhere('name', 'LIKE', '%QRIS%')
                                ->orWhereIn('key', ['SP', 'NQ', 'DQ', 'SQ', 'LQ', 'qris', 'QRIS']);
                        } else {
                            $query->where('name', 'LIKE', '%QRIS%')
                                ->orWhereIn('key', ['SP', 'NQ', 'DQ', 'SQ', 'LQ', 'qris', 'QRIS']);
                        }
                    })
                    ->update(['category_id' => $catQris]);
            }

            // 3b. Virtual Account
            if ($catVa) {
                $vaKeys = ['VA', 'BC', 'BR', 'M1', 'M2', 'BN', 'BT', 'B1', 'A1', 'I1', 'AG', 'NC', 'bank_transfer', 'echannel', 'bca_va', 'bri_va', 'bni_va', 'mandiri_va', 'permata_va', 'cimb_va'];
                $vaTypes = ['va', 'VA', 'virtual_account', 'virtual-account', 'bank_transfer', 'permata', 'bca', 'bni', 'bri', 'mandiri', 'cimb'];

                DB::table('payment_methods')
                    ->whereNull('category_id')
                    ->where(function ($query) use ($hasType, $vaKeys, $vaTypes) {
                        if ($hasType) {
                            $query->whereIn('type', $vaTypes)
                                ->orWhereIn('key', $vaKeys)
                                ->orWhere('name', 'LIKE', '%VA%')
                                ->orWhere('name', 'LIKE', '%Virtual Account%');
                        } else {
                            $query->whereIn('key', $vaKeys)
                                ->orWhere('name', 'LIKE', '%VA%')
                                ->orWhere('name', 'LIKE', '%Virtual Account%');
                        }
                    })
                    ->update(['category_id' => $catVa]);
            }

            // 3c. Credit Card
            if ($catCc) {
                $ccKeys = ['VC', 'credit_card', 'card', 'CARD_STRIPE'];
                $ccTypes = ['cc', 'CC', 'card', 'credit_card'];

                DB::table('payment_methods')
                    ->whereNull('category_id')
                    ->where(function ($query) use ($hasType, $ccKeys, $ccTypes) {
                        if ($hasType) {
                            $query->whereIn('type', $ccTypes)
                                ->orWhereIn('key', $ccKeys)
                                ->orWhere('name', 'LIKE', '%Credit Card%')
                                ->orWhere('name', 'LIKE', '%CREDIT CARD%');
                        } else {
                            $query->whereIn('key', $ccKeys)
                                ->orWhere('name', 'LIKE', '%Credit Card%')
                                ->orWhere('name', 'LIKE', '%CREDIT CARD%');
                        }
                    })
                    ->update(['category_id' => $catCc]);
            }

            // 3d. E-Wallet / Digital Apps
            if ($catEwallet) {
                $ewalletKeys = ['OV', 'DA', 'SA', 'LA', 'SL', 'gopay', 'shopeepay', 'bca_klikpay', 'bca_klikbca', 'cimb_clicks', 'danamon_online', 'akulaku', 'bri_epay', 'DN'];
                $ewalletTypes = ['ewallet', 'e-wallet', 'gopay', 'ovo', 'dana', 'shopeepay', 'linkaja'];

                DB::table('payment_methods')
                    ->whereNull('category_id')
                    ->where(function ($query) use ($hasType, $ewalletKeys, $ewalletTypes) {
                        if ($hasType) {
                            $query->whereIn('type', $ewalletTypes)
                                ->orWhereIn('key', $ewalletKeys)
                                ->orWhere('name', 'LIKE', '%OVO%')
                                ->orWhere('name', 'LIKE', '%DANA%')
                                ->orWhere('name', 'LIKE', '%SHOPEEPAY APP%')
                                ->orWhere('name', 'LIKE', '%LINKAJA APP%')
                                ->orWhere('name', 'LIKE', '%PAYLATER%');
                        } else {
                            $query->whereIn('key', $ewalletKeys)
                                ->orWhere('name', 'LIKE', '%OVO%')
                                ->orWhere('name', 'LIKE', '%DANA%')
                                ->orWhere('name', 'LIKE', '%SHOPEEPAY APP%')
                                ->orWhere('name', 'LIKE', '%LINKAJA APP%')
                                ->orWhere('name', 'LIKE', '%PAYLATER%');
                        }
                    })
                    ->update(['category_id' => $catEwallet]);
            }

            // 3e. Retail / Convenience Store
            if ($catRetail) {
                $retailKeys = ['IR', 'FT', 'cstore', 'INDOMARET', 'ALFAMART'];
                $retailTypes = ['retail', 'cstore', 'c-store'];

                DB::table('payment_methods')
                    ->whereNull('category_id')
                    ->where(function ($query) use ($hasType, $retailKeys, $retailTypes) {
                        if ($hasType) {
                            $query->whereIn('type', $retailTypes)
                                ->orWhereIn('key', $retailKeys)
                                ->orWhere('name', 'LIKE', '%INDOMARET%')
                                ->orWhere('name', 'LIKE', '%ALFAMART%')
                                ->orWhere('name', 'LIKE', '%RETAIL%');
                        } else {
                            $query->whereIn('key', $retailKeys)
                                ->orWhere('name', 'LIKE', '%INDOMARET%')
                                ->orWhere('name', 'LIKE', '%ALFAMART%')
                                ->orWhere('name', 'LIKE', '%RETAIL%');
                        }
                    })
                    ->update(['category_id' => $catRetail]);
            }

            // Fallback: match by direct key if type is still matching any category key
            if ($hasType) {
                DB::statement("
                    UPDATE payment_methods pm
                    JOIN payment_categories pc ON pc.key = pm.type
                    SET pm.category_id = pc.id
                    WHERE pm.category_id IS NULL
                ");
            }
        }

        // 4. Safely drop type column if it exists
        if (Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('type')->nullable()->after('key');
            });
        }

        if (Schema::hasTable('payment_categories') && Schema::hasColumn('payment_methods', 'category_id')) {
            DB::statement("
                UPDATE payment_methods pm
                JOIN payment_categories pc ON pc.id = pm.category_id
                SET pm.type = pc.key
                WHERE pm.type IS NULL
            ");
        }

        if (Schema::hasColumn('payment_methods', 'category_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
    }
};
