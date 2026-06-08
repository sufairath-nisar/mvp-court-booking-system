<?php

namespace App\Enums;

enum BookingStatus: string
{
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';

    /**
     * Get all status values as a plain array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
