<?php

namespace App\Domain\Grouping;

use App\Models\Booking;
use App\Models\LessonDay;
use App\Models\Student;

/** One student on one lesson day, and the group they are in (if any). */
final class GroupEntry
{
    public function __construct(
        public LessonDay $lessonDay,
        public Student $student,
        public Booking $booking,
        public int $age,
        public ?int $groupId,
    ) {}

    public function key(): string
    {
        return "{$this->lessonDay->id}-{$this->student->id}";
    }
}
