<?php

namespace App\Enums;

enum PaymentModeType: string
{
    case sandbox = 'sandbox';
    case prod = 'prod';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (PaymentModeType $mode): string => $mode->value,
            self::cases(),
        );
    }

    public static function fromName(string|PaymentModeType|null $value): ?PaymentModeType
    {
        if ($value instanceof PaymentModeType || $value === null) {
            return $value;
        }

        return self::tryFrom(strtolower($value));
    }

    public function isProduction(): bool
    {
        return $this === self::prod;
    }
}
