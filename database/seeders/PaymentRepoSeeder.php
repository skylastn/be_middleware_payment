<?php

namespace Database\Seeders;

use App\Enums\PaymentModeType;
use App\Models\PaymentGateway;
use App\Models\PaymentRepository;
use Illuminate\Database\Seeder;

class PaymentRepoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $duitku = PaymentGateway::create([
            'key' => 'duitku',
            'name' => 'Duitku',
            'description' => 'Duitku',
        ]);
        $xendit = PaymentGateway::create([
            'key' => 'xendit',
            'name' => 'Xendit',
            'description' => 'Xendit',
        ]);
        $spnpay = PaymentGateway::create([
            'key' => 'spnpay',
            'name' => 'SPNPay',
            'description' => 'SPNPay',
        ]);
        $midtrans = PaymentGateway::create([
            'key' => 'midtrans',
            'name' => 'Midtrans',
            'description' => 'Midtrans',
        ]);

        foreach (PaymentModeType::cases() as $key) {
            PaymentRepository::create([
                'payment_gateway_id' => $duitku->id,
                'mode' => $key,
                'value' => json_encode([
                    'duitku_mk' => '7d07b87ceeb77cbdb80asdw3b35ee9e36364',
                    'duitku_mc' => 'DS21819',
                ]),
            ]);

            PaymentRepository::create([
                'payment_gateway_id' => $xendit->id,
                'mode' => $key,
                'value' => json_encode([
                    'xendit_publickey' => 'xnd_public_development_2vNvQzut12UkEmLSY01ITVIIAdL...',
                    'xendit_secretkey' => 'xnd_development_7KBmCLH0wEJ6dPn50b8U02ToOFEvNetLO...',
                    'xendit_tokencallback' => '5e949270191351b30093c72bdea90555/7ea80195a90ce8131...',
                ]),
            ]);

            PaymentRepository::create([
                'payment_gateway_id' => $spnpay->id,
                'mode' => $key,
                'value' => json_encode([
                    'url_spnpay' => $key == 'sandbox' ? 'https://api.sanbox.cronosengine.com/api' : 'https://partner.api.spnpay.com/api',
                    'spnpay_token' => '8qJKU9FA17kuBpLaWU3cRg1nDuh8rGLy',
                    'spnpay_secretkey' => 'SC-3DEIWDRNN77WGMasdwaQ',
                ]),
            ]);

            PaymentRepository::create([
                'payment_gateway_id' => $midtrans->id,
                'mode' => $key,
                'value' => json_encode([
                    'midtrans_serverkey' => 'SB-Mid-server-7d07b87ceeb77cbdb80asdw3b35ee9e3',
                    'midtrans_clientkey' => 'SB-Mid-client-7d07b87ceeb77cbdb80asdw3b35ee9e3',
                ]),
            ]);
        }
    }
}
