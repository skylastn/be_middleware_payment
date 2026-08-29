<?php

namespace Database\Seeders;

use App\Model\Entity\PaymentCategory;
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
                'value' => '',
            ],
            [
                'key' => 'NQ',
                'name' => 'Nobu QRIS',
                'category_key' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ],
            [
                'key' => 'DQ',
                'name' => 'Dana QRIS',
                'category_key' => 'qris',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ],
            [
                'key' => 'PAPRIKA_QRIS',
                'name' => 'Paprika QRIS',
                'category_key' => 'qris',
                'from' => 'paprika',
                'bankCode' => '',
                'value' => '',
            ],

            // Virtual Account Channels (category: va)
            [
                'key' => 'BC',
                'name' => 'BCA VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bca',
                'value' => '',
            ],
            [
                'key' => 'BR',
                'name' => 'BRIVA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bri',
                'value' => '',
            ],
            [
                'key' => 'M2',
                'name' => 'Mandiri VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'mandiri',
                'value' => '',
            ],
            [
                'key' => 'BN',
                'name' => 'BNI VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'bni',
                'value' => '',
            ],
            [
                'key' => 'BT',
                'name' => 'Permata VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'permata',
                'value' => '',
            ],
            [
                'key' => 'B1',
                'name' => 'CIMB Niaga VA',
                'category_key' => 'va',
                'from' => 'duitku',
                'bankCode' => 'cimb',
                'value' => '',
            ],

            // Credit Card Channels (category: cc)
            [
                'key' => 'VC',
                'name' => 'Credit Card',
                'category_key' => 'cc',
                'from' => 'duitku',
                'bankCode' => '',
                'value' => '',
            ],
            [
                'key' => 'CARD_STRIPE',
                'name' => 'Credit / Debit Card (Stripe)',
                'category_key' => 'cc',
                'from' => 'stripe',
                'bankCode' => '',
                'value' => '',
            ],
        ];

        foreach ($methods as $method) {
            $categoryId = $categories->get($method['category_key'])?->id;

            PaymentMethod::updateOrCreate(
                [
                    'key' => $method['key'],
                    'from' => $method['from'],
                ],
                [
                    'name' => $method['name'],
                    'category_id' => $categoryId,
                    'bankCode' => $method['bankCode'],
                    'value' => $method['value'],
                ]
            );
        }
    }
}
