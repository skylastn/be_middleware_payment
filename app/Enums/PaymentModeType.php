<?php

namespace App\Enums;

enum PaymentModeType: string
{
    case sandbox = 'sandbox';
    case prod = 'prod';
}
