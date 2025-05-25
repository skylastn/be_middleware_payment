<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMethod::create([
            'key' => 'SP',
            'name' => 'ShopeePay QRIS',
            'type' => 'qris',
            'from' => 'duitku',
            'bankCode' => '',
            'value' => '',
        ]);
        PaymentMethod::create([
            'key' => 'NQ',
            'name' => 'Nobu QRIS',
            'type' => 'qris',
            'from' => 'duitku',
            'bankCode' => '',
            'value' => '',
        ]);
        PaymentMethod::create([
            'key' => 'DQ',
            'name' => 'Dana QRIS',
            'type' => 'qris',
            'from' => 'duitku',
            'bankCode' => '',
            'value' => '',
        ]);
        PaymentMethod::create([
            'key' => 'BR',
            'name' => 'BRIVA',
            'type' => 'virtual_account',
            'from' => 'duitku',
            'bankCode' => '',
            'value' => '',
        ]);
        PaymentMethod::create([
            'key' => 'BC',
            'name' => 'BCA VA',
            'type' => 'virtual_account',
            'from' => 'duitku',
            'bankCode' => '',
            'value' => '',
        ]);
    }
}
