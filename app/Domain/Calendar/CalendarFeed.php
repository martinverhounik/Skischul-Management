<?php

namespace App\Domain\Calendar;

use App\Domain\Grouping\AssignmentGuard;
use App\Enums\CourseType;
use App\Models\CalendarEvent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LessonDay;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the planning calendar shows between two days (US-26):
 * groups and private lessons with their instructor (straight from
 * Grouping, US-24), students still waiting for a group, and standalone
 * calendar entries. Cancelled bookings are left out.
 *
 * Filters: a course (price table) and/or an instructor.
 */
class CalendarFeed
{
    public function __construct(private School $school) {}

    public static function for(School $school): self
    {
        return new self($school->loadMissing('timeslots'));
    }

    /**
     * @return Collection<string, Collection<int, CalendarEntry>> keyed by Y-m-d, every day present
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?int $courseId = null, ?int $instructorId = null): Collection
    {
        $entries = collect()
            ->concat($this->groups($from, $to))
            ->concat($instructorId ? [] : $this->ungrouped($from, $to))
            ->concat($this->privateLessons($from, $to))
            ->concat($courseId ? [] : $this->events($from, $to))
            ->filter(fn (CalendarEntry $e) => ! $courseId || in_array($courseId, $e->courseIds, true))
            ->filter(fn (CalendarEntry $e) => ! $instructorId || $e->instructorId === $instructorId);

        $days = collect();
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days->put($d->toDateString(), collect());
        }
        foreach ($entries as $entry) {
            $days->get($entry->date->toDateString())?->push($entry);
        }

        return $days->map(fn (Collection $list) => $list
            ->sortBy(fn (CalendarEntry $e) => ($e->allDay ? '0' : '1').$e->from.$e->title)
            ->values());
    }

    /** @return list<CalendarEntry> */
    private function groups(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $guard = AssignmentGuard::for($this->school);
        $groups = Group::query()->with('instructor', 'sport', 'ageGroup', 'meetingPoint')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get();

        // Which courses (price tables) the members of each group booked.
        $members = GroupMember::query()
            ->whereIn('group_id', $groups->pluck('id'))
            ->whereHas('lessonDay.line.booking', fn ($q) => $q->whereNull('cancelled_at'))
            ->with('lessonDay.line:id,price_table_id')
            ->get()
            ->groupBy('group_id');

        return $groups->map(function (Group $g) use ($guard, $members) {
            [$start, $end] = $guard->slotTimes($g->timeslot_key);
            $list = $members->get($g->id, collect());

            return new CalendarEntry(
                kind: CalendarEntry::GROUP,
                id: $g->id,
                date: CarbonImmutable::parse($g->date->toDateString()),
                from: $start,
                to: $end,
                allDay: false,
                title: $g->sport->name.' · '.$g->ageGroup->name.' · '.$g->name,
                detail: trans_choice(':count student|:count students', $list->count())
                    .($g->meetingPoint ? ' · '.$g->meetingPoint->name : ''),
                instructorId: $g->instructor_id,
                instructorName: $g->instructor?->fullName(),
                courseIds: $list->map(fn ($m) => $m->lessonDay->line->price_table_id)->unique()->values()->all(),
                sportId: $g->sport_id,
            );
        })->all();
    }

    /**
     * Group-course students without a group, one entry per day, time and
     * sport, so the calendar shows that grouping is still to do.
     *
     * @return list<CalendarEntry>
     */
    private function ungrouped(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $guard = AssignmentGuard::for($this->school);
        $days = $this->lessonDays($from, $to)->filter(fn (LessonDay $d) => $d->line->course_type === CourseType::Group);
        $placed = GroupMember::query()->whereIn('lesson_day_id', $days->pluck('id'))->get()
            ->map(fn ($m) => "{$m->lesson_day_id}-{$m->student_id}")->flip();

        $buckets = [];
        foreach ($days as $day) {
            $open = $day->line->students->reject(fn ($s) => $placed->has("{$day->id}-{$s->id}"))->count();
            if ($open === 0) {
                continue;
            }
            $key = $day->date->toDateString().'|'.$day->timeslot_key->value.'|'.$day->line->sport_id;
            $buckets[$key] ??= ['day' => $day, 'count' => 0, 'courses' => []];
            $buckets[$key]['count'] += $open;
            $buckets[$key]['courses'][] = $day->line->price_table_id;
        }

        return collect($buckets)->map(function ($b) use ($guard) {
            $day = $b['day'];
            [$start, $end] = $guard->slotTimes($day->timeslot_key);

            return new CalendarEntry(
                kind: CalendarEntry::UNGROUPED,
                id: null,
                date: CarbonImmutable::parse($day->date->toDateString()),
                from: $start,
                to: $end,
                allDay: false,
                title: $day->line->sport->name.' · '.__('not grouped yet'),
                detail: trans_choice(':count student without a group|:count students without a group', $b['count']),
                courseIds: array_values(array_unique($b['courses'])),
                sportId: $day->line->sport_id,
            );
        })->values()->all();
    }

    /** @return list<CalendarEntry> */
    private function privateLessons(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->lessonDays($from, $to)
            ->filter(fn (LessonDay $d) => $d->line->course_type->isHourly())
            ->map(fn (LessonDay $d) => new CalendarEntry(
                kind: CalendarEntry::PRIVATE,
                id: $d->id,
                date: CarbonImmutable::parse($d->date->toDateString()),
                from: substr((string) $d->starts_at, 0, 5),
                to: substr((string) $d->ends_at, 0, 5),
                allDay: false,
                title: $d->line->priceTable->displayName(),
                detail: $d->line->students->map->fullName()->implode(', '),
                instructorId: $d->instructor_id,
                instructorName: $d->instructor?->fullName(),
                courseIds: [$d->line->price_table_id],
                sportId: $d->line->sport_id,
            ))->values()->all();
    }

    /** @return list<CalendarEntry> */
    private function events(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        $events = CalendarEvent::query()->with('instructor')->between($from, $to)->get();
        foreach ($events as $event) {
            $first = CarbonImmutable::parse($event->starts_on->toDateString())->max($from);
            $last = CarbonImmutable::parse($event->ends_on->toDateString())->min($to);
            for ($d = $first; $d->lte($last); $d = $d->addDay()) {
                $out[] = new CalendarEntry(
                    kind: CalendarEntry::EVENT,
                    id: $event->id,
                    date: $d,
                    from: $event->fromLabel(),
                    to: $event->toLabel(),
                    allDay: $event->isAllDay(),
                    title: $event->title,
                    detail: (string) $event->notes,
                    instructorId: $event->instructor_id,
                    instructorName: $event->instructor?->fullName(),
                );
            }
        }

        return $out;
    }

    /** @return Collection<int, LessonDay> */
    private function lessonDays(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LessonDay::query()
            ->with(['line.priceTable', 'line.sport', 'line.students', 'instructor'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereHas('line.booking', fn ($q) => $q->whereNull('cancelled_at'))
            ->get();
    }
}
