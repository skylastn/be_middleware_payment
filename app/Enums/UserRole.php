<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            fn (UserRole $role): string => $role->value,
            self::cases(),
        );
    }
}
