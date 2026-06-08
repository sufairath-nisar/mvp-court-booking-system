<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CONSUMER = 'consumer';

    /**
     * Get all role values as a plain array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
