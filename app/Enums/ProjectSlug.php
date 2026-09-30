<?php

namespace App\Enums;

enum ProjectSlug: string
{
    case MIDTRANS = 'midtrans';
    case XENDIT = 'xendit';
    case DUITKU = 'duitku';
    case SPNPAY = 'spnpay';
    case STRIPE = 'stripe';
    case PAPRIKA = 'paprika';
    case AGI = 'bank_agi';

    public static function values(): array
    {
        return array_map(fn (self $slug): string => $slug->value, self::cases());
    }

    public static function toArray(): array
    {
        return [
            self::MIDTRANS,
            self::XENDIT,
            self::DUITKU,
            self::SPNPAY,
            self::STRIPE,
            self::PAPRIKA,
            self::AGI,
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
            case self::STRIPE:
                return 'Stripe';
            case self::PAPRIKA:
                return 'Paprika';
            case self::AGI:
                return 'Bank Artha Graha Internasional';
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

        switch (strtolower(trim($value))) {
            case self::MIDTRANS->value:
                return ProjectSlug::MIDTRANS;
            case self::XENDIT->value:
                return ProjectSlug::XENDIT;
            case self::DUITKU->value:
                return ProjectSlug::DUITKU;
            case self::SPNPAY->value:
                return ProjectSlug::SPNPAY;
            case self::STRIPE->value:
                return ProjectSlug::STRIPE;
            case self::PAPRIKA->value:
                return ProjectSlug::PAPRIKA;
            case self::AGI->value:
            case 'agi':
            case 'artha-graha':
            case 'bank-artha-graha':
                return ProjectSlug::AGI;
            default:
                return ProjectSlug::DUITKU;
        }
    }
}
