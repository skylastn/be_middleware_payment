<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class PaymentTestCase extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('telescope.storage.database.connection', 'sqlite');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_06_01_000001_add_role_to_users_table.php',
            '2022_09_02_195636_create_projects_table.php',
            '2022_09_03_053052_create_orders_table.php',
            '2024_02_19_061840_create_payment_categories_table.php',
            '2025_10_21_144858_create_payment_repositories_table.php',
            '2025_10_21_153618_create_payment_gateways_table.php',
            '2025_10_22_223759_add_payment_repository_id_to_order_table.php',
            '2025_10_23_143825_add_name_to_payment_repositories_table.php',
            '2026_08_30_000001_create_order_histories_table.php',
            '2026_08_30_000003_add_value_to_orders_table.php',
            '2026_09_08_000003_add_return_url_to_orders_table.php',
            '2026_09_09_000001_add_amount_to_orders_table.php',
            '2026_09_10_000001_add_name_to_orders_table.php',
            '2026_09_18_000002_add_expired_at_to_orders_table.php',
            '2026_09_30_000001_add_gateway_references_to_orders_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('category_id')->nullable();
            $table->uuid('payment_gateway_id')->nullable();
            $table->string('bankCode')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
