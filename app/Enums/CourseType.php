<?php

namespace App\Enums;

/**
 * The three kinds of courses (US-07). Group courses are priced per day and
 * participant; private lessons and guiding per hour, with a fee for every
 * extra participant.
 */
enum CourseType: string
{
    case Group = 'group';
    case Private = 'private';
    case Guiding = 'guiding';

    public function label(): string
    {
        return match ($this) {
            self::Group => __('Group course'),
            self::Private => __('Private lesson'),
            self::Guiding => __('Guiding'),
        };
    }

    /** Private and guiding work the same way: hours, base price + extra participants. */
    public function isHourly(): bool
    {
        return $this !== self::Group;
    }

    /** "days" or "hours", what the grid rows count. */
    public function unitLabel(int $count): string
    {
        return $this->isHourly()
            ? trans_choice(':count hour|:count hours', $count)
            : trans_choice(':count day|:count days', $count);
    }
}
