<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'PENDING';
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (OrderStatus $status): string => $status->value,
            self::cases(),
        );
    }

    public static function fromName(string|OrderStatus|null $value): ?OrderStatus
    {
        if ($value instanceof OrderStatus || $value === null) {
            return $value;
        }

        return match (strtoupper($value)) {
            self::PENDING->value => self::PENDING,
            self::FAILED->value => self::FAILED,
            self::SUCCESS->value => self::SUCCESS,
            self::EXPIRED->value, 'EXPIRE' => self::EXPIRED,
            'PAID', 'SETTLEMENT', 'SETTLED', 'CAPTURE' => self::SUCCESS,
            'DENY', 'DENIED', 'CANCEL', 'CANCELED', 'CANCELLED', 'VOIDED' => self::FAILED,
            default => null,
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::FAILED, self::EXPIRED], true);
    }
}
