<?php

namespace App\Enums;

/**
 * The fixed course timeslots of a school (US-06). Their start and end times
 * are edited per school; price tables reference them as "time options".
 */
enum TimeslotKey: string
{
    case FullDay = 'full_day';
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Lunch = 'lunch';
    /** Earliest start and latest end for private lessons. */
    case PrivateDay = 'private_day';

    public function label(): string
    {
        return match ($this) {
            self::FullDay => __('Full day'),
            self::Morning => __('Morning'),
            self::Afternoon => __('Afternoon'),
            self::Lunch => __('Lunch'),
            self::PrivateDay => __('Private lessons (day window)'),
        };
    }

    /** @return array{0: string, 1: string} default start and end (H:i) */
    public function defaultTimes(): array
    {
        return match ($this) {
            self::FullDay => ['10:00', '15:30'],
            self::Morning => ['10:00', '12:00'],
            self::Afternoon => ['13:30', '15:30'],
            self::Lunch => ['12:00', '13:30'],
            self::PrivateDay => ['09:00', '16:30'],
        };
    }

    /** Options a price table can activate (US-07): not lunch, not the private window. */
    public function isTimeOption(): bool
    {
        return in_array($this, [self::FullDay, self::Morning, self::Afternoon], true);
    }
}
