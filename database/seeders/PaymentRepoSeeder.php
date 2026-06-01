<?php

namespace Database\Seeders;

use App\Enums\PaymentModeType;
use App\Model\Entity\PaymentGateway;
use App\Model\Entity\PaymentRepository;
use Illuminate\Database\Seeder;

class PaymentRepoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Payment Gateways ---
        $duitku = PaymentGateway::firstOrCreate(
            ['key' => 'duitku'],
            [
                'name' => 'Duitku',
                'description' => 'Duitku',
            ]
        );

        $xendit = PaymentGateway::firstOrCreate(
            ['key' => 'xendit'],
            [
                'name' => 'Xendit',
                'description' => 'Xendit',
            ]
        );

        $spnpay = PaymentGateway::firstOrCreate(
            ['key' => 'spnpay'],
            [
                'name' => 'SPNPay',
                'description' => 'SPNPay',
            ]
        );

        $midtrans = PaymentGateway::firstOrCreate(
            ['key' => 'midtrans'],
            [
                'name' => 'Midtrans',
                'description' => 'Midtrans',
            ]
        );

        // --- Payment Repositories ---
        foreach (PaymentModeType::cases() as $mode) {

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $duitku->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_duitku_'. $mode->value,
                    'value' => json_encode([
                        'duitku_mk' => '7d07b87ceeb77cbdb80asdw3b35ee9e36364',
                        'duitku_mc' => 'DS21819',
                    ]),
                ]
            );

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $xendit->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_xendit_'. $mode->value,
                    'value' => json_encode([
                        'xendit_publickey' => 'xnd_public_development_2vNvQzut12UkEmLSY01ITVIIAdL...',
                        'xendit_secretkey' => 'xnd_development_7KBmCLH0wEJ6dPn50b8U02ToOFEvNetLO...',
                        'xendit_tokencallback' => '5e949270191351b30093c72bdea90555/7ea80195a90ce8131...',
                    ]),
                ]
            );

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $spnpay->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_spnpay_'. $mode->value,
                    'value' => json_encode([
                        'url_spnpay' => $mode->value === 'sandbox'
                            ? 'https://api.sanbox.cronosengine.com/api'
                            : 'https://partner.api.spnpay.com/api',
                        'spnpay_token' => '8qJKU9FA17kuBpLaWU3cRg1nDuh8rGLy',
                        'spnpay_secretkey' => 'SC-3DEIWDRNN77WGMasdwaQ',
                    ]),
                ]
            );

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $midtrans->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_midtrans_'. $mode->value,
                    'value' => json_encode([
                        'midtrans_serverkey' => 'SB-Mid-server-7d07b87ceeb77cbdb80asdw3b35ee9e3',
                        'midtrans_clientkey'  => 'SB-Mid-client-7d07b87ceeb77cbdb80asdw3b35ee9e3',
                        'url_midtrans' =>  $mode->value === 'sandbox' ?
                            'https://app.sandbox.midtrans.com/snap/v1/transactions'
                            : 'https://app.midtrans.com/snap/v1/transactions',
                    ]),
                ]
            );
        }
    }
}
