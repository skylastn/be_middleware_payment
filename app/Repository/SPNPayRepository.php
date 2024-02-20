<?php

namespace App\Repository;


class SPNPayRepository
{
    static function responseOrderFilter(object $data): array
    {
        $value['id']     = $data->id ?? '';
        if (empty($data->virtualAccount)) {
            array_merge($value, self::fillEmptyResponseOrder('virtualAccount', $data));
        }
        if (empty($data->qris)) {
            array_merge($value, self::fillEmptyResponseOrder('qris', $data));
        }
        if (empty($data->eWallet)) {
            array_merge($value, self::fillEmptyResponseOrder('eWallet', $data));
        }
        if (empty($data->retail)) {
            array_merge($value, self::fillEmptyResponseOrder('retail', $data));
        }
        if (empty($data->creditCard)) {
            array_merge($value, self::fillEmptyResponseOrder('creditCard', $data));
        }
        return $value;
    }

    static function fillEmptyResponseOrder(string $type, object $data): array
    {
        switch ($type) {
            case 'virtualAccount':
                return array(
                    'virtualAccount' => array(
                        'bankCode'      => $data->bankCode ?? '',
                        'vaNumber'      => $data->vaNumber ?? '',
                        'viewName'      => $data->viewName ?? '',
                    ),
                );
            case 'qris':
                return array(
                    'qris' => array(
                        'content'       => $data->content ?? '',
                        'url'           => $data->url ?? '',
                    ),
                );
            case 'eWallet':
                return array(
                    'eWallet' => array(
                        'viewName'      => $data->viewName ?? '',
                        'channel'       => $data->channel ?? '',
                        'url'           => $data->url ?? '',
                    ),
                );
            case 'retail':
                return array(
                    'eWallet' => array(
                        'viewName'      => $data->viewName ?? '',
                        'channel'       => $data->channel ?? '',
                        'paymentCode'   => $data->paymentCode ?? '',
                    ),
                );
            case 'creditCard':
                return array(
                    'creditCard' => array(
                        'url'      => $data->url ?? '',
                    ),
                );
            default:
                return [];
        }
    }
}
