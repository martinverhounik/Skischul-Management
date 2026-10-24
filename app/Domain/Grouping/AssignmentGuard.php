<?php

namespace App\Domain\Grouping;

use App\Enums\TimeslotKey;
use App\Models\Group;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\School;
use App\Models\Sport;
use Carbon\CarbonImmutable;

/**
 * Rules for assigning an instructor (US-24, US-30):
 * - only active instructors (hard)
 * - no two assignments at overlapping times on the same day (hard)
 * - the instructor should teach the sport (warning, plan question 13)
 *
 * Step 8 adds standalone calendar events (sick leave, day off) to the
 * overlap check.
 */
class AssignmentGuard
{
    public function __construct(private School $school) {}

    public static function for(School $school): self
    {
        return new self($school->loadMissing('timeslots'));
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function check(Instructor $instructor, CarbonImmutable $date, string $from, string $to, ?Sport $sport, ?int $exceptGroupId = null, ?int $exceptLessonDayId = null): array
    {
        $errors = [];
        $warnings = [];

        if (! $instructor->is_active) {
            $errors[] = __(':name is inactive and cannot be assigned.', ['name' => $instructor->fullName()]);
        }

        foreach ($this->busy($instructor, $date, $exceptGroupId, $exceptLessonDayId) as $busy) {
            if ($from < $busy['to'] && $busy['from'] < $to) {
                $errors[] = __(':name is already busy :from–:to (:what).', [
                    'name' => $instructor->fullName(), 'from' => $busy['from'], 'to' => $busy['to'], 'what' => $busy['what'],
                ]);
            }
        }

        if ($sport && ! $instructor->sports->contains('id', $sport->id)) {
            $warnings[] = __(':name does not teach :sport.', ['name' => $instructor->fullName(), 'sport' => $sport->name]);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** Start and end (H:i) of a group's timeslot. */
    public function slotTimes(TimeslotKey $key): array
    {
        $slot = $this->school->timeslot($key);

        return $slot ? [$slot->startsAtLabel(), $slot->endsAtLabel()] : ['00:00', '23:59'];
    }

    /** @return list<array{from: string, to: string, what: string}> */
    private function busy(Instructor $instructor, CarbonImmutable $date, ?int $exceptGroupId, ?int $exceptLessonDayId): array
    {
        $busy = [];

        $groups = Group::query()->with('sport')
            ->where('instructor_id', $instructor->id)
            ->whereDate('date', $date->toDateString())
            ->when($exceptGroupId, fn ($q) => $q->whereKeyNot($exceptGroupId))
            ->get();
        foreach ($groups as $group) {
            [$from, $to] = $this->slotTimes($group->timeslot_key);
            $busy[] = ['from' => $from, 'to' => $to, 'what' => $group->name.' · '.$group->sport->name];
        }

        $lessons = LessonDay::query()->with('line.priceTable')
            ->where('instructor_id', $instructor->id)
            ->whereDate('date', $date->toDateString())
            ->when($exceptLessonDayId, fn ($q) => $q->whereKeyNot($exceptLessonDayId))
            ->whereHas('line.booking', fn ($q) => $q->whereNull('cancelled_at'))
            ->get();
        foreach ($lessons as $lesson) {
            $busy[] = [
                'from' => substr((string) $lesson->starts_at, 0, 5),
                'to' => substr((string) $lesson->ends_at, 0, 5),
                'what' => $lesson->line->priceTable->displayName(),
            ];
        }

        return $busy;
    }
}
