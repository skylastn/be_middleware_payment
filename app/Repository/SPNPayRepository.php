<?php

namespace App\Repository;

use Exception;

class SPNPayRepository
{
    static function responseOrderFilter(object $data): array
    {
        $value['id']                = $data->id ?? '';
        $value['merchantRef']       = $data->merchantRef ?? '';
        $value['status']            = $data->status ?? '';
        $value['feePayer']          = $data->feePayer ?? '';
        $value['amount']            = $data->feePayer ?? 0;
        $value['fee']               = $data->fee ?? 0;
        $value['totalAmount']       = $data->totalAmount ?? 0;
        $value['expiredDate']       = $data->expiredDate ?? '';
        $value['additionalInfo']    = array("callback" => $data->additionalInfo->callback);

        $value = array_merge($value, self::fillEmptyResponseOrder('virtualAccount', $data));
        $value = array_merge($value, self::fillEmptyResponseOrder('qris', $data));
        $value = array_merge($value, self::fillEmptyResponseOrder('eWallet', $data));
        $value = array_merge($value, self::fillEmptyResponseOrder('retail', $data));
        $value = array_merge($value, self::fillEmptyResponseOrder('creditCard', $data));
        return $value;
    }

    static function fillEmptyResponseOrder(string $type, object $data): array
    {
        switch ($type) {
            case 'virtualAccount':
                return array(
                    'virtualAccount' => array(
                        'bankCode'      => $data->virtualAccount->bankCode ?? '',
                        'vaNumber'      => $data->virtualAccount->vaNumber ?? '',
                        'viewName'      => $data->virtualAccount->viewName ?? '',
                    ),
                );
            case 'qris':
                return array(
                    'qris' => array(
                        'content'       => $data->qris->content ?? '',
                        'url'           => $data->qris->url ?? '',
                    ),
                );
            case 'eWallet':
                return array(
                    'eWallet' => array(
                        'viewName'      => $data->eWallet->viewName ?? '',
                        'channel'       => $data->eWallet->channel ?? '',
                        'url'           => $data->eWallet->url ?? '',
                    ),
                );
            case 'retail':
                return array(
                    'retail' => array(
                        'viewName'      => $data->retail->viewName ?? '',
                        'channel'       => $data->retail->channel ?? '',
                        'paymentCode'   => $data->retail->paymentCode ?? '',
                    ),
                );
            case 'creditCard':
                return array(
                    'creditCard' => array(
                        'url'      => $data->creditCard->url ?? '',
                    ),
                );
            default:
                return [];
        }
    }
}
