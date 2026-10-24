<?php

namespace App\Domain\Calendar;

use Carbon\CarbonImmutable;

/**
 * One block in the planning calendar (US-26). Course entries are read
 * live from Grouping, so the calendar and Grouping always show the same
 * assignment; only "event" entries are stored for the calendar itself.
 */
final class CalendarEntry
{
    public const GROUP = 'group';         // a group with (or without) its instructor

    public const UNGROUPED = 'ungrouped'; // group-course students not in a group yet

    public const PRIVATE = 'private';     // a private lesson / guiding

    public const EVENT = 'event';         // standalone entry: day off, meeting …

    /**
     * @param  list<int>  $courseIds  price tables behind this entry (course filter)
     */
    public function __construct(
        public string $kind,
        public ?int $id,
        public CarbonImmutable $date,
        public string $from,
        public string $to,
        public bool $allDay,
        public string $title,
        public string $detail,
        public ?int $instructorId = null,
        public ?string $instructorName = null,
        public array $courseIds = [],
        public ?int $sportId = null,
    ) {}

    public function key(): string
    {
        return $this->kind.'-'.($this->id ?? md5($this->title.$this->from)).'-'.$this->date->toDateString();
    }

    /** A course entry nobody teaches yet (shown in red). */
    public function needsInstructor(): bool
    {
        return $this->kind !== self::EVENT && $this->instructorId === null;
    }

    public function isCourse(): bool
    {
        return $this->kind !== self::EVENT;
    }

    /** Minutes since midnight, for positioning in the day/week grid. */
    public function startMinutes(): int
    {
        return self::minutes($this->from);
    }

    public function endMinutes(): int
    {
        return self::minutes($this->to);
    }

    public static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}
