<?php

namespace Database\Seeders;

use App\Model\Entity\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentMethod::firstOrCreate(
            ['key' => 'SP'],
            [
                'name' => 'ShopeePay QRIS',
                'type' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ]
        );

        PaymentMethod::firstOrCreate(
            ['key' => 'NQ'],
            [
                'name' => 'Nobu QRIS',
                'type' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ]
        );

        PaymentMethod::firstOrCreate(
            ['key' => 'DQ'],
            [
                'name' => 'Dana QRIS',
                'type' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ]
        );

        PaymentMethod::firstOrCreate(
            ['key' => 'BR'],
            [
                'name' => 'BRIVA',
                'type' => 'virtual_account',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ]
        );

        PaymentMethod::firstOrCreate(
            ['key' => 'BC'],
            [
                'name' => 'BCA VA',
                'type' => 'virtual_account',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ]
        );
    }
}
