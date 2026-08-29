<?php

namespace Tests\Feature;

use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentMethod;
use Database\Seeders\PaymentCategorySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionMigrationCompatibilityTest extends TestCase
{
    public function test_migration_compatibility_with_db1_state(): void
    {
        // Recreate legacy DB 1 state
        if (! Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('type')->nullable();
            });
        }
        if (Schema::hasColumn('payment_methods', 'category_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }

        // DB 1 payment categories: virtual-account & qris
        DB::table('payment_categories')->truncate();
        DB::table('payment_categories')->insert([
            ['id' => 1, 'key' => 'virtual-account', 'title' => 'Virtual Account', 'detail' => 'Bank Virtual Account', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'key' => 'qris', 'title' => 'QRIS', 'detail' => 'QRIS E-Wallet', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // DB 1 payment methods sample
        DB::table('payment_methods')->truncate();
        DB::table('payment_methods')->insert([
            ['id' => 1, 'key' => 'credit_card', 'type' => '', 'name' => '', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'key' => 'gopay', 'type' => '', 'name' => '', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'key' => 'qris', 'type' => '', 'name' => '', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'key' => 'bank_transfer', 'type' => 'permata', 'name' => '', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'key' => 'VA', 'type' => '', 'name' => 'MAYBANK VA', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'key' => 'VC', 'type' => '', 'name' => 'CREDIT CARD', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 29, 'key' => 'SP', 'type' => '', 'name' => 'SHOPEEPAY QRIS', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 35, 'key' => 'BC', 'type' => '', 'name' => 'BCA VA', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 36, 'key' => 'IR', 'type' => '', 'name' => 'INDOMARET', 'from' => 'duitku', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the migration
        $migration = require database_path('migrations/2026_08_30_000002_update_payment_methods_replace_type_with_category_id.php');
        $migration->up();

        // Verify columns
        $this->assertTrue(Schema::hasColumn('payment_methods', 'category_id'));
        $this->assertFalse(Schema::hasColumn('payment_methods', 'type'));

        // Verify normalized categories
        $vaCat = PaymentCategory::where('key', 'va')->first();
        $qrisCat = PaymentCategory::where('key', 'qris')->first();
        $ccCat = PaymentCategory::where('key', 'cc')->first();
        $ewalletCat = PaymentCategory::where('key', 'ewallet')->first();
        $retailCat = PaymentCategory::where('key', 'retail')->first();

        $this->assertNotNull($vaCat);
        $this->assertNotNull($qrisCat);
        $this->assertNotNull($ccCat);
        $this->assertNotNull($ewalletCat);
        $this->assertNotNull($retailCat);

        // Verify methods mapped correctly
        $this->assertEquals($ccCat->id, PaymentMethod::find(1)->category_id);
        $this->assertEquals($ewalletCat->id, PaymentMethod::find(2)->category_id);
        $this->assertEquals($qrisCat->id, PaymentMethod::find(3)->category_id);
        $this->assertEquals($vaCat->id, PaymentMethod::find(5)->category_id);
        $this->assertEquals($vaCat->id, PaymentMethod::find(18)->category_id);
        $this->assertEquals($ccCat->id, PaymentMethod::find(20)->category_id);
        $this->assertEquals($qrisCat->id, PaymentMethod::find(29)->category_id);
        $this->assertEquals($vaCat->id, PaymentMethod::find(35)->category_id);
        $this->assertEquals($retailCat->id, PaymentMethod::find(36)->category_id);
    }

    public function test_migration_compatibility_with_db2_state(): void
    {
        // Recreate legacy DB 2 state (empty categories, 6 methods with type)
        if (! Schema::hasColumn('payment_methods', 'type')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->string('type')->nullable();
            });
        }
        if (Schema::hasColumn('payment_methods', 'category_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }

        // DB 2 has empty payment_categories
        DB::table('payment_categories')->truncate();

        // DB 2 payment methods
        DB::table('payment_methods')->truncate();
        DB::table('payment_methods')->insert([
            ['id' => 1, 'key' => 'SP', 'type' => 'qris', 'from' => 'duitku', 'name' => 'ShopeePay QRIS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'key' => 'NQ', 'type' => 'qris', 'from' => 'duitku', 'name' => 'Nobu QRIS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'key' => 'DQ', 'type' => 'qris', 'from' => 'duitku', 'name' => 'Dana QRIS', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'key' => 'BR', 'type' => 'virtual_account', 'from' => 'duitku', 'name' => 'BRIVA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'key' => 'BC', 'type' => 'virtual_account', 'from' => 'duitku', 'name' => 'BCA VA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'key' => 'SQ', 'type' => 'qris', 'from' => 'duitku', 'name' => 'Nusapay QRIS', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Run the migration
        $migration = require database_path('migrations/2026_08_30_000002_update_payment_methods_replace_type_with_category_id.php');
        $migration->up();

        // Verify categories were created
        $vaCat = PaymentCategory::where('key', 'va')->first();
        $qrisCat = PaymentCategory::where('key', 'qris')->first();

        $this->assertNotNull($vaCat);
        $this->assertNotNull($qrisCat);

        // Verify DB 2 records mapped correctly
        $this->assertEquals($qrisCat->id, PaymentMethod::find(1)->category_id);
        $this->assertEquals($qrisCat->id, PaymentMethod::find(2)->category_id);
        $this->assertEquals($qrisCat->id, PaymentMethod::find(3)->category_id);
        $this->assertEquals($vaCat->id, PaymentMethod::find(4)->category_id);
        $this->assertEquals($vaCat->id, PaymentMethod::find(5)->category_id);
        $this->assertEquals($qrisCat->id, PaymentMethod::find(6)->category_id);

        // Re-seed standard seeders for subsequent tests
        $this->seed(PaymentCategorySeeder::class);
        $this->seed(PaymentMethodSeeder::class);
    }
}
