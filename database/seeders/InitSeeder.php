<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class InitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            SettingsTableSeeder::class,
            PaymentGatewaySeeder::class,
            PaymentCategorySeeder::class,
            PaymentMethodSeeder::class,
            PaymentRepoSeeder::class,
        ]);
    }
}
