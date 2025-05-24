<?php

namespace App\Enums;

enum NetworkStatus: string
{
    case UNAUTHORIZED = 'unauthorized';
    case SUCCESS = 'success';
    case ERROR = 'error';

    static function getNetworkStatusFromNetworkCode(NetworkCode $code = NetworkCode::SUCCESS): NetworkStatus
    {
        switch ($code) {
            case NetworkCode::SUCCESS:
                return NetworkStatus::SUCCESS;
                break;
            case NetworkCode::UNAUTHORIZED:
                return NetworkStatus::UNAUTHORIZED;
                break;
            default:
                return NetworkStatus::ERROR;
        }
    }
}
