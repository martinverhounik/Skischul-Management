<?php

namespace App\Enums;

/** Where a booking came from. The platform imports come later. */
enum BookingSource: string
{
    case Office = 'office';
    case Book2Ski = 'book2ski';
    case CheckYeti = 'checkyeti';

    public function label(): string
    {
        return match ($this) {
            self::Office => __('Office'),
            self::Book2Ski => 'book2ski',
            self::CheckYeti => 'CheckYeti',
        };
    }
}
