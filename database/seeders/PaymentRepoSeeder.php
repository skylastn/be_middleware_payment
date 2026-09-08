<?php

namespace Database\Seeders;

use App\Enums\PaymentModeType;
use App\Enums\SurchargeMode;
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

        $stripe = PaymentGateway::firstOrCreate(
            ['key' => 'stripe'],
            [
                'name' => 'Stripe',
                'description' => 'Stripe',
            ]
        );

        $paprika = PaymentGateway::firstOrCreate(
            ['key' => 'paprika'],
            [
                'name' => 'Paprika',
                'description' => 'Paprika',
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
                    'key' => 'default_duitku_'.$mode->value,
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
                    'key' => 'default_xendit_'.$mode->value,
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
                    'key' => 'default_spnpay_'.$mode->value,
                    'value' => json_encode([
                        'url_spnpay' => $mode === PaymentModeType::sandbox
                            ? 'https://api.sanbox.cronosengine.com/api'
                            : 'https://partner.api.spnpay.com/api',
                        'spnpay_token' => 'xxx',
                        'spnpay_secretkey' => 'SC-xxx',
                    ]),
                ]
            );

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $midtrans->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_midtrans_'.$mode->value,
                    'value' => json_encode([
                        'midtrans_serverkey' => 'SB-Mid-server-xxx',
                        'midtrans_clientkey' => 'SB-Mid-client-xxx',
                        'url_midtrans' => $mode === PaymentModeType::sandbox ?
                            'https://app.sandbox.midtrans.com/snap/v1/transactions'
                            : 'https://app.midtrans.com/snap/v1/transactions',
                    ]),
                ]
            );

            $stripeRepo = PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $stripe->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_stripe_'.$mode->value,
                    'value' => json_encode([
                        'stripe_secretkey' => $mode === PaymentModeType::sandbox
                            ? 'sk_test_...'
                            : 'sk_live_...',
                        'stripe_publishablekey' => $mode === PaymentModeType::sandbox
                            ? 'pk_test_...'
                            : 'pk_live_...',
                        'stripe_webhooksecret' => $mode === PaymentModeType::sandbox
                            ? 'whsec_test_...'
                            : 'whsec_...',
                        'surcharge_mode' => SurchargeMode::MIDDLEWARE_CALC->value,
                        'surcharge_percent' => 2.9,
                        'surcharge_fixed' => 2000,
                        'surcharge_label' => 'Processing Fee',
                    ]),
                ]
            );

            // Check if existing Stripe record is missing surcharge fields, and append them without touching existing keys
            $stripeValue = is_array($stripeRepo->value) ? $stripeRepo->value : (json_decode((string) $stripeRepo->value, true) ?: []);
            $defaultSurchargeFields = [
                'surcharge_mode' => SurchargeMode::MIDDLEWARE_CALC->value,
                'surcharge_percent' => 2.9,
                'surcharge_fixed' => 2000,
                'surcharge_label' => 'Processing Fee',
            ];

            $needsUpdate = false;
            foreach ($defaultSurchargeFields as $surchargeKey => $defaultValue) {
                if (! array_key_exists($surchargeKey, $stripeValue)) {
                    $stripeValue[$surchargeKey] = $defaultValue;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $stripeRepo->value = $stripeValue;
                $stripeRepo->save();
            }

            PaymentRepository::firstOrCreate(
                [
                    'payment_gateway_id' => $paprika->id,
                    'mode' => $mode->value,
                ],
                [
                    'key' => 'default_paprika_'.$mode->value,
                    'value' => json_encode([
                        'api_key' => 'paprika_api_key_xxx',
                        'api_secret' => 'paprika_api_secret_xxx',
                        'base_url' => $mode === PaymentModeType::sandbox
                            ? 'https://sandbox.paprika.id'
                            : 'https://api.paprika.id',
                        'private_key' => '-----BEGIN RSA PRIVATE KEY-----...',
                    ]),
                ]
            );
        }
    }
}
