<?php

namespace Database\Seeders;

use App\Model\Entity\PaymentCategory;
use Illuminate\Database\Seeder;

class PaymentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
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

        foreach ($categories as $category) {
            PaymentCategory::updateOrCreate(
                ['key' => $category['key']],
                [
                    'title' => $category['title'],
                    'detail' => $category['detail'],
                ]
            );
        }
    }
}
