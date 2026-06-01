<?php

namespace App\Enums;

enum ProjectSlug: string
{
    case MIDTRANS = 'midtrans';
    case XENDIT = 'xendit';
    case DUITKU = 'duitku';
    case SPNPAY = 'spnpay';

    public static function toArray(): array
    {
        return [
            self::MIDTRANS,
            self::XENDIT,
            self::DUITKU,
            self::SPNPAY,
        ];
    }

    public static function toLabel(ProjectSlug $value): string
    {
        switch ($value) {
            case self::MIDTRANS:
                return 'Midtrans';
            case self::XENDIT:
                return 'Xendit';
            case self::DUITKU:
                return 'Duitku';
            case self::SPNPAY:
                return 'SPNPay';
            default:
                return 'Duitku';
                break;
        }
    }


    public static function fromName(string|ProjectSlug $value): ProjectSlug
    {
        if ($value instanceof ProjectSlug) {
            return $value;
        }

        switch ($value) {
            case self::MIDTRANS->value:
                return ProjectSlug::MIDTRANS;
            case self::XENDIT->value:
                return ProjectSlug::XENDIT;
            case self::DUITKU->value:
                return ProjectSlug::DUITKU;
            case self::SPNPAY->value:
                return ProjectSlug::SPNPAY;
            default:
                return ProjectSlug::DUITKU;
        }
    }
}
