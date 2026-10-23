<?php

namespace App\Enums;

/**
 * Back-office roles (US-27).
 *
 * Owner: full access, including settings, price tables, users and
 *        instructor financial data.
 * Staff: day-to-day work (bookings, students, grouping).
 */
enum Role: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner / Administrator'),
            self::Staff => __('Staff'),
        };
    }

    /** @return array<string, string> value => label, for select inputs */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
