<?php

namespace Database\Seeders;

use App\Model\Entity\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gateways = [
            [
                'key' => 'duitku',
                'name' => 'Duitku',
                'description' => 'Duitku Payment Gateway',
            ],
            [
                'key' => 'xendit',
                'name' => 'Xendit',
                'description' => 'Xendit Payment Gateway',
            ],
            [
                'key' => 'midtrans',
                'name' => 'Midtrans',
                'description' => 'Midtrans Payment Gateway',
            ],
            [
                'key' => 'spnpay',
                'name' => 'SPNPay',
                'description' => 'SPNPay Payment Gateway',
            ],
            [
                'key' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Stripe Payment Gateway',
            ],
            [
                'key' => 'paprika',
                'name' => 'Paprika',
                'description' => 'Paprika Payment Gateway',
            ],
        ];

        foreach ($gateways as $gw) {
            PaymentGateway::firstOrCreate(
                ['key' => $gw['key']],
                [
                    'name' => $gw['name'],
                    'description' => $gw['description'],
                ]
            );
        }
    }
}
