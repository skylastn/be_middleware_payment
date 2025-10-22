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
            SettingsTableSeeder::class,
            PaymentMethodSeeder::class,
            PaymentRepoSeeder::class,
        ]);
    }
}
