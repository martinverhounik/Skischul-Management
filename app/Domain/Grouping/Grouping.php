<?php

namespace App\Domain\Grouping;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\AgeGroup;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LessonDay;
use App\Models\PriceTable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads the grouping picture for a day or a week (US-22, US-23):
 * group-course students counted by sport and age group (and time),
 * and private lessons listed by date and time. Cancelled bookings are
 * left out everywhere (US-16).
 */
class Grouping
{
    /**
     * Group-course cohorts of one day, sorted by sport, age group and time.
     *
     * @return Collection<string, Cohort>
     */
    public function cohorts(CarbonImmutable $date): Collection
    {
        $days = $this->lessonDays($date, $date)
            ->filter(fn (LessonDay $d) => $d->line->course_type === CourseType::Group);

        $memberships = GroupMember::query()
            ->whereIn('lesson_day_id', $days->pluck('id'))
            ->get()
            ->keyBy(fn ($m) => "{$m->lesson_day_id}-{$m->student_id}");

        $cohorts = collect();
        foreach ($days as $day) {
            $line = $day->line;
            foreach ($line->students as $student) {
                $ageGroup = $this->ageGroupFor($line->priceTable, (int) $student->pivot->age_at_start);
                if (! $ageGroup) {
                    continue;
                }
                $key = Cohort::makeKey($day->timeslot_key, $line->sport_id, $ageGroup->id);
                $cohort = $cohorts->get($key) ?? new Cohort($date, $day->timeslot_key, $line->sport, $ageGroup, $line->priceTable->max_participants);
                $cohort->maxParticipants = min($cohort->maxParticipants, $line->priceTable->max_participants);
                $cohort->entries->push(new GroupEntry(
                    $day, $student, $line->booking, (int) $student->pivot->age_at_start,
                    $memberships->get("{$day->id}-{$student->id}")?->group_id,
                ));
                $cohorts->put($key, $cohort);
            }
        }

        // Groups without students (e.g. just created) still show up.
        $groups = Group::query()->with('instructor', 'meetingPoint', 'sport', 'ageGroup')
            ->whereDate('date', $date->toDateString())->orderBy('name')->get();
        foreach ($groups as $group) {
            $key = Cohort::makeKey($group->timeslot_key, $group->sport_id, $group->age_group_id);
            $cohort = $cohorts->get($key) ?? new Cohort($date, $group->timeslot_key, $group->sport, $group->ageGroup, PHP_INT_MAX);
            $cohort->groups->push($group);
            $cohorts->put($key, $cohort);
        }

        // A student moved into a group of another age group (e.g. a Mini
        // skiing with the Kids) is counted where their group is.
        $groupCohort = $groups->mapWithKeys(fn ($g) => [$g->id => Cohort::makeKey($g->timeslot_key, $g->sport_id, $g->age_group_id)]);
        foreach ($cohorts as $key => $cohort) {
            foreach ($cohort->entries as $i => $entry) {
                $target = $entry->groupId ? $groupCohort->get($entry->groupId) : null;
                if ($target && $target !== $key && $cohorts->has($target)) {
                    $cohorts->get($target)->entries->push($entry);
                    $cohort->entries->forget($i);
                }
            }
        }

        $order = array_map(fn ($k) => $k->value, TimeslotKey::cases());

        return $cohorts->each(function (Cohort $c) {
            if ($c->maxParticipants === PHP_INT_MAX) {
                $c->maxParticipants = 1;
            }
            $c->entries = $c->entries->sortBy([
                fn ($a, $b) => ($a->student->skillLevel?->position ?? 999) <=> ($b->student->skillLevel?->position ?? 999),
                fn ($a, $b) => strcmp($a->student->last_name, $b->student->last_name),
            ])->values();
        })->sortBy(fn (Cohort $c) => sprintf('%05d-%03d-%d', $c->sport->position, $c->ageGroup->min_age, array_search($c->timeslot->value, $order, true)));
    }

    /**
     * Private lessons and guiding of one day, by time.
     *
     * @return Collection<int, LessonDay>
     */
    public function privateLessons(CarbonImmutable $date): Collection
    {
        return $this->lessonDays($date, $date)
            ->filter(fn (LessonDay $d) => $d->line->course_type->isHourly())
            ->sortBy(fn (LessonDay $d) => $d->starts_at)
            ->values();
    }

    /**
     * US-23 week view: per day, the cohorts with their counts.
     *
     * @return Collection<string, array{date: CarbonImmutable, cohorts: Collection<string, Cohort>, private: Collection<int, LessonDay>}>
     */
    public function week(CarbonImmutable $start): Collection
    {
        return collect(range(0, 6))->mapWithKeys(function (int $i) use ($start) {
            $date = $start->addDays($i);

            return [$date->toDateString() => [
                'date' => $date,
                'cohorts' => $this->cohorts($date),
                'private' => $this->privateLessons($date),
            ]];
        });
    }

    /**
     * The age group a student counts in: the course's age group that fits
     * their age; if none fits (booked with "book anyway", e.g. a Mini skiing
     * with the Kids), the course's youngest age group.
     */
    public function ageGroupFor(PriceTable $table, int $age): ?AgeGroup
    {
        $groups = $table->ageGroups->sortBy('min_age');

        return $groups->first(fn (AgeGroup $g) => $g->includesAge($age)) ?? $groups->first();
    }

    /** @return Collection<int, LessonDay> */
    private function lessonDays(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LessonDay::query()
            ->with([
                'line.booking', 'line.sport', 'line.priceTable.ageGroups',
                'line.students.skillLevel', 'instructor', 'meetingPoint',
            ])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->whereHas('line.booking', fn ($q) => $q->whereNull('cancelled_at'))
            ->get();
    }
}
