<?php

namespace App\Enums;

enum PayoutGateway: string
{
    case Stripe = 'stripe';
    case Duitku = 'duitku';
    case Xendit = 'xendit';
    case Midtrans = 'midtrans';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $g): string => $g->value, self::cases());
    }

    public static function fromName(string|self|null $value): ?self
    {
        if ($value instanceof self || $value === null) {
            return $value;
        }

        return self::tryFrom(strtolower($value));
    }
}
