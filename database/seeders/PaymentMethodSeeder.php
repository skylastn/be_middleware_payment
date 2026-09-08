<?php

namespace Database\Seeders;

use App\Model\Entity\PaymentCategory;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = PaymentCategory::all()->keyBy('key');

        $methods = [
            // QRIS Channels (category: qris)
            [
                'key' => 'SP',
                'name' => 'ShopeePay QRIS',
                'category_key' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
            ],
            [
                'key' => 'NQ',
                'name' => 'Nobu QRIS',
                'category_key' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
            ],
            [
                'key' => 'DQ',
                'name' => 'Dana QRIS',
                'category_key' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
            ],
            [
                'key' => 'PAPRIKA_QRIS',
                'name' => 'Paprika QRIS',
                'category_key' => 'qris',
                'from' => 'paprika',
                'bankCode' => '',
            ],

            // Virtual Account Channels (category: va)
            [
                'key' => 'BC',
                'name' => 'BCA VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bca',
            ],
            [
                'key' => 'BR',
                'name' => 'BRIVA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bri',
            ],
            [
                'key' => 'M2',
                'name' => 'Mandiri VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'mandiri',
            ],
            [
                'key' => 'BN',
                'name' => 'BNI VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bni',
            ],
            [
                'key' => 'BT',
                'name' => 'Permata VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'permata',
            ],
            [
                'key' => 'B1',
                'name' => 'CIMB Niaga VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'cimb',
            ],
            [
                'key' => 'VA_PERMATA',
                'name' => 'Paprika Permata VA',
                'category_key' => 'va',
                'from' => 'paprika',
                'bankCode' => '013',
            ],
            [
                'key' => 'VA_MAYBANK',
                'name' => 'Paprika Maybank VA',
                'category_key' => 'va',
                'from' => 'paprika',
                'bankCode' => '016',
            ],
            [
                'key' => 'VA_AGRAHA',
                'name' => 'Paprika Artha Graha VA',
                'category_key' => 'va',
                'from' => 'paprika',
                'bankCode' => '037',
            ],

            // Credit Card Channels (category: cc)
            [
                'key' => 'VC',
                'name' => 'Credit Card',
                'category_key' => 'cc',
                'from' => 'duitku',
                'bankCode' => '',
            ],
            [
                'key' => 'CARD_STRIPE',
                'name' => 'Credit / Debit Card (Stripe)',
                'category_key' => 'cc',
                'from' => 'stripe',
                'bankCode' => '',
            ],
        ];

        $gateways = PaymentGateway::all()->keyBy('key');

        foreach ($methods as $method) {
            $categoryId = $categories->get($method['category_key'])?->id;
            $gatewayKey = $method['from'];
            $gateway = $gateways->get($gatewayKey);

            if (! $gateway) {
                $gateway = PaymentGateway::firstOrCreate(
                    ['key' => $gatewayKey],
                    [
                        'name' => ucfirst($gatewayKey),
                        'description' => ucfirst($gatewayKey) . ' Gateway',
                    ]
                );
                $gateways->put($gatewayKey, $gateway);
            }

            PaymentMethod::updateOrCreate(
                [
                    'key' => $method['key'],
                    'payment_gateway_id' => $gateway->id,
                ],
                [
                    'name' => $method['name'],
                    'category_id' => $categoryId,
                    'bankCode' => $method['bankCode'],
                ]
            );
        }
    }
}
