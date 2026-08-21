<?php

namespace App\Enums;

enum SurchargeMode: string
{
    case NONE = 'none';
    case MIDDLEWARE_CALC = 'middleware_calc';
    case STRIPE_AUTOMATIC = 'stripe_automatic';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (SurchargeMode $mode): string => $mode->value,
            self::cases(),
        );
    }

    public static function fromName(string|SurchargeMode|null $value): ?SurchargeMode
    {
        if ($value instanceof SurchargeMode || $value === null) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'none', 'off', 'disable', 'disabled', '0', 'false' => self::NONE,
            'middleware_calc', 'middleware', 'calc', 'gross_up', 'grossup' => self::MIDDLEWARE_CALC,
            'stripe_automatic', 'stripe', 'automatic', 'auto' => self::STRIPE_AUTOMATIC,
            default => self::tryFrom($normalized),
        };
    }

    public function isMiddlewareCalc(): bool
    {
        return $this === self::MIDDLEWARE_CALC;
    }

    public function isStripeAutomatic(): bool
    {
        return $this === self::STRIPE_AUTOMATIC;
    }

    public function isNone(): bool
    {
        return $this === self::NONE;
    }
}
