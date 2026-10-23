<?php

namespace App\Enums;

/** No online payments yet: the office marks bookings as paid by hand (US-15). */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
        };
    }
}
