<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
        };
    }
}
