<?php

namespace App\Domain\Grouping;

use App\Enums\TimeslotKey;
use App\Models\CalendarEvent;
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
 * - no assignment during the instructor's own calendar entry, e.g. sick
 *   leave or a day off (hard, US-26)
 * - the instructor should teach the sport (warning, plan question 13)
 *
 * Calendar entries use conflicts() the other way round: a new entry for an
 * instructor may not overlap their course assignments.
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

        foreach ($this->conflicts($instructor, $date, $from, $to, $exceptGroupId, $exceptLessonDayId, withEvents: true) as $busy) {
            $errors[] = match (true) {
                $busy['allDay'] ?? false => __(':name is not available on this day (:what).', ['name' => $instructor->fullName(), 'what' => $busy['what']]),
                $busy['event'] ?? false => __(':name is not available :from–:to (:what).', [
                    'name' => $instructor->fullName(), 'from' => $busy['from'], 'to' => $busy['to'], 'what' => $busy['what'],
                ]),
                default => __(':name is already busy :from–:to (:what).', [
                    'name' => $instructor->fullName(), 'from' => $busy['from'], 'to' => $busy['to'], 'what' => $busy['what'],
                ]),
            };
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

    /**
     * What the instructor already does on that day between from and to
     * (H:i; back-to-back is fine).
     *
     * @return list<array{from: string, to: string, what: string, event?: bool, allDay?: bool}>
     */
    public function conflicts(Instructor $instructor, CarbonImmutable $date, string $from, string $to, ?int $exceptGroupId = null, ?int $exceptLessonDayId = null, bool $withEvents = false): array
    {
        return array_values(array_filter(
            $this->busy($instructor, $date, $exceptGroupId, $exceptLessonDayId, $withEvents),
            fn ($busy) => $from < $busy['to'] && $busy['from'] < $to,
        ));
    }

    /** @return list<array{from: string, to: string, what: string, event?: bool, allDay?: bool}> */
    private function busy(Instructor $instructor, CarbonImmutable $date, ?int $exceptGroupId, ?int $exceptLessonDayId, bool $withEvents): array
    {
        $busy = [];

        if ($withEvents) {
            $events = CalendarEvent::query()->where('instructor_id', $instructor->id)->between($date, $date)->get();
            foreach ($events as $event) {
                $busy[] = [
                    'from' => $event->fromLabel(),
                    'to' => $event->toLabel(),
                    'what' => $event->title,
                    'event' => true,
                    'allDay' => $event->isAllDay(),
                ];
            }
        }

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
