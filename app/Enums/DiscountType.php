<?php

namespace App\Enums;

enum DiscountType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::Percent => __('Percentage'),
        };
    }
}
