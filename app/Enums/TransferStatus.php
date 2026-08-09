<?php

namespace App\Enums;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(
            fn (TransferStatus $status): string => $status->value,
            self::cases(),
        );
    }

    public static function fromName(string|TransferStatus|null $value): ?TransferStatus
    {
        if ($value instanceof TransferStatus || $value === null) {
            return $value;
        }

        return self::tryFrom(strtolower($value));
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }
}
