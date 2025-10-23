<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $listInsert = [
        //     [
        //         'key' => 'xendit_publickey',
        //         'value' => 'xnd_public_development_2vNvQzut12UkEmLSY01ITVIIAdL...',
        //         'created_at' => '2022-09-03 00:21:05',
        //         'updated_at' => '2022-09-03 00:21:05',
        //     ],
        //     [
        //         'key' => 'xendit_secretkey_sanbox',
        //         'value' => 'xnd_development_7KBmCLH0wEJ6dPn50b8U02ToOFEvNetLO...',
        //         'created_at' => '2022-09-03 00:21:05',
        //         'updated_at' => '2022-09-03 00:21:05',
        //     ],
        //     [
        //         'key' => 'xendit_tokencallback',
        //         'value' => '5e949270191351b30093c72bdea90555/7ea80195a90ce8131...',
        //         'created_at' => '2022-09-03 00:21:05',
        //         'updated_at' => '2022-09-03 00:21:05',
        //     ],
        //     [
        //         'key' => 'xendit_secretkey_prod',
        //         'value' => 'xnd_production_eUSSwz6WGUSHEECOMJhZf962j5tUs5OCHPD...',
        //         'created_at' => '2022-09-03 00:21:05',
        //         'updated_at' => '2022-09-03 00:21:05',
        //     ],
        //     [
        //         'key' => 'xendit_tokencallback_sanbox',
        //         'value' => '8r4BUoSh7ZZ8nv6BkZf6cvwdEnxhu12312',
        //         'created_at' => '2022-09-03 00:21:05',
        //         'updated_at' => '2022-09-03 00:21:05',
        //     ],
        //     [
        //         'key' => 'url_success',
        //         'value' => 'https://test.com/test',
        //         'created_at' => '2022-09-04 16:32:44',
        //         'updated_at' => '2022-09-04 16:32:45',
        //     ],
        //     [
        //         'key' => 'url_spnpay_sanbox',
        //         'value' => 'https://api.sanbox.cronosengine.com/api',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'url_spnpay_prod',
        //         'value' => 'https://partner.api.spnpay.com/api',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'spnpay_token_sanbox',
        //         'value' => '8qJKU9FA17kuBpLaWU3cRg1nDuh8rGLy',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'spnpay_token_prod',
        //         'value' => 'jb1WOwYKoJY4GKamUMHN7DizlnaAAYY',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'spnpay_secretkey_sanbox',
        //         'value' => 'SC-3DEIWDRNN77WGMasdwaQ',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'spnpay_secretkey_prod',
        //         'value' => 'SB-WCXXGYP97SMRBWasdwaM6',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'duitku_mk_sandbox',
        //         'value' => '7d07b87ceeb77cbdb80asdw3b35ee9e36364',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'duitku_mc_sandbox',
        //         'value' => 'DS21819',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'duitku_mk_prod',
        //         'value' => '7d07b87ceeb77cbdb80asdw3b35ee9e36364',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        //     [
        //         'key' => 'duitku_mc_prod',
        //         'value' => 'DS21819',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ],
        // ];
        // foreach ($listInsert as $key) {
        //     $find = DB::table('settings')->where('key', $key['key'])->first();
        //     if (!FormatHelper::isNotEmpty($find)) {
        //         DB::table('settings')->insert($key);
        //     }
        // }
    }
}
