<?php

namespace App\Enums;

enum NetworkCode: int
{
    case SUCCESS = 200;
    case UNAUTHORIZED = 401;
    case ERROR = 400;

    static function fromCode(int $code): NetworkCode
    {
        switch ($code) {
            case 200:
            case 201:
                return NetworkCode::SUCCESS;
            case 401:
            case 401:
                return NetworkCode::UNAUTHORIZED;
            default:
                return NetworkCode::ERROR;
        }
    }
}
