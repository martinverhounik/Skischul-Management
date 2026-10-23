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
 * - A multi-day course keeps its normal window (start + n days). Closed days
 *   inside the window get no lesson; the course resumes the next day.
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
     * The lesson dates of a course starting on $start that runs for $days
     * calendar days. Closed days in between are skipped.
     *
     * @return Collection<int, CarbonImmutable>
     *
     * @throws CalendarRuleViolation when the start is not allowed or the
     *                               window runs past the season end
     */
    public function lessonDays(CarbonInterface $start, int $days): Collection
    {
        $start = CarbonImmutable::parse($start->toDateString());

        if ($days < 1) {
            throw new CalendarRuleViolation(__('A course needs at least one day.'));
        }

        if (! $this->isInSeason($start)) {
            throw new CalendarRuleViolation(__('The school is not open on this day (outside the season).'));
        }

        if ($this->isClosed($start)) {
            throw new CalendarRuleViolation(__('The school is closed on this day.'));
        }

        $end = $start->addDays($days - 1);
        if (! $this->isInSeason($end)) {
            throw new CalendarRuleViolation(__('The course would run past the end of the season (:date).', [
                'date' => $this->school->season_ends_on->isoFormat('L'),
            ]));
        }

        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => $start->addDays($offset))
            ->reject(fn (CarbonImmutable $day) => $this->isClosed($day))
            ->values();
    }
}
