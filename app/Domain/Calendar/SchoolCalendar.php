<?php

namespace App\Domain\Calendar;

use App\Models\School;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The master availability filter (US-01, US-30): which days the school
 * operates. Used by booking, grouping and the calendar.
 *
 * Rules:
 * - Without a configured season nothing is bookable.
 * - A course may only START on an operating day (in season, not closed).
 * - A closed day is treated as if it did not exist: a course of N days has
 *   its lessons on the next N operating days (owner decision, step 3).
 * - A course never runs past its latest end day (the price table's end
 *   weekday, e.g. Friday). If closed days leave fewer operating days before
 *   that day, the course simply has fewer lessons that week, and only those
 *   are charged.
 * - No course may run past the season end.
 */
class SchoolCalendar
{
    /** @var array<string, true> closed dates as Y-m-d */
    private array $closed;

    public function __construct(private School $school)
    {
        $this->closed = $school->closedDays()
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [CarbonImmutable::parse($date)->toDateString() => true])
            ->all();
    }

    public static function for(School $school): self
    {
        return new self($school);
    }

    public function isInSeason(CarbonInterface $date): bool
    {
        if (! $this->school->hasSeason()) {
            return false;
        }

        $day = $date->toDateString();

        return $day >= $this->school->season_starts_on->toDateString()
            && $day <= $this->school->season_ends_on->toDateString();
    }

    public function isClosed(CarbonInterface $date): bool
    {
        return isset($this->closed[$date->toDateString()]);
    }

    public function isOperatingDay(CarbonInterface $date): bool
    {
        return $this->isInSeason($date) && ! $this->isClosed($date);
    }

    public function canStartOn(CarbonInterface $date): bool
    {
        return $this->isOperatingDay($date);
    }

    /**
     * The lesson dates of a course of $days days starting on $start: the
     * next $days operating days, skipping closed days, but never after
     * $latestEnd (when given). The result can therefore be shorter than
     * $days when closed days fall into that week.
     *
     * @return Collection<int, CarbonImmutable>
     *
     * @throws CalendarRuleViolation when the start is not allowed or the
     *                               course would run past the season end
     */
    public function lessonDays(CarbonInterface $start, int $days, ?CarbonInterface $latestEnd = null): Collection
    {
        $start = CarbonImmutable::parse($start->toDateString());
        $latestEnd = $latestEnd ? CarbonImmutable::parse($latestEnd->toDateString()) : null;

        if ($days < 1) {
            throw new CalendarRuleViolation(__('A course needs at least one day.'));
        }

        if (! $this->isInSeason($start)) {
            throw new CalendarRuleViolation(__('The school is not open on this day (outside the season).'));
        }

        if ($this->isClosed($start)) {
            throw new CalendarRuleViolation(__('The school is closed on this day.'));
        }

        $lessons = collect();
        $day = $start;

        while ($lessons->count() < $days) {
            if ($latestEnd && $day->gt($latestEnd)) {
                break; // fewer lessons this week because of closed days
            }

            if (! $this->isInSeason($day)) {
                throw new CalendarRuleViolation(__('The course would run past the end of the season (:date).', [
                    'date' => $this->school->season_ends_on->isoFormat('L'),
                ]));
            }

            if (! $this->isClosed($day)) {
                $lessons->push($day);
            }

            $day = $day->addDay();
        }

        return $lessons;
    }
}
