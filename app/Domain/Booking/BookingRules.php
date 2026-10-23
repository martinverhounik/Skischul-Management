<?php

namespace App\Domain\Booking;

use App\Domain\Calendar\CalendarRuleViolation;
use App\Domain\Calendar\SchoolCalendar;
use App\Domain\Pricing\PriceCalculator;
use App\Domain\Pricing\PricingException;
use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\PriceTable;
use App\Models\School;
use App\Models\Student;
use Carbon\CarbonImmutable;

/**
 * Every rule for booking one course (US-10, US-11, US-30), in one place.
 * Used live while the office configures a course, again in the cart, and
 * once more when the order is placed.
 *
 * A cart item is a plain array:
 *   price_table_id, sport_id, students: [{id, age_override}],
 *   group:   timeslot_key, start_date (Y-m-d), days
 *   private: start_date (Y-m-d), start_time (H:i), hours
 */
class BookingRules
{
    public function __construct(private PriceCalculator $calculator) {}

    public function check(School $school, array $item): Quote
    {
        $quote = new Quote($item);

        $table = PriceTable::query()
            ->with('sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices')
            ->find((int) ($item['price_table_id'] ?? 0));

        if (! $table || ! $table->is_active) {
            return $quote->fail('price_table', __('This course is not offered any more.'));
        }
        $quote->priceTable = $table;

        $quote->sport = $table->sports->firstWhere('id', (int) ($item['sport_id'] ?? 0));
        if (! $quote->sport) {
            $quote->fail('sport', __('Please choose a sport.'));
        }

        $start = $this->parseDate($item['start_date'] ?? null);
        if (! $start) {
            $quote->fail('start_date', __('Please choose a date.'));
        }

        $calendar = SchoolCalendar::for($school);

        if ($start) {
            $table->course_type === CourseType::Group
                ? $this->checkGroup($quote, $school, $calendar, $table, $start)
                : $this->checkHourly($quote, $school, $calendar, $table, $start);
        }

        $this->checkParticipants($quote, $table, $start);

        if ($quote->errors === [] && $quote->option) {
            try {
                $units = $table->course_type === CourseType::Group ? $quote->lessonDates->count() : $quote->units;
                $quote->priceCents = $this->calculator->quote($table, $quote->option, $units, count($quote->participants));
            } catch (PricingException $e) {
                $quote->fail('price', $e->getMessage());
            }
        }

        return $quote;
    }

    private function checkGroup(Quote $quote, School $school, SchoolCalendar $calendar, PriceTable $table, CarbonImmutable $start): void
    {
        $quote->option = TimeslotKey::tryFrom((string) ($quote->item['timeslot_key'] ?? ''));
        if (! $quote->option || ! in_array($quote->option, $table->activeTimeslotKeys(), true)) {
            $quote->option = null;
            $quote->fail('timeslot_key', __('Please choose a time.'));
        }

        $days = (int) ($quote->item['days'] ?? 0);
        $quote->units = $days;
        if ($days < $table->min_days || $days > $table->max_days) {
            $quote->fail('days', __('Please choose between :min and :max days.', ['min' => $table->min_days, 'max' => $table->max_days]));

            return;
        }

        if (! in_array($start->dayOfWeek, $table->start_weekdays ?? [], true)) {
            $quote->fail('start_date', __('This course cannot start on a :day.', ['day' => $start->isoFormat('dddd')]));

            return;
        }

        try {
            $quote->lessonDates = $calendar->lessonDays($start, $days, $this->latestEnd($table, $start));
        } catch (CalendarRuleViolation $e) {
            $quote->fail('start_date', $e->getMessage());
        }
    }

    private function checkHourly(Quote $quote, School $school, SchoolCalendar $calendar, PriceTable $table, CarbonImmutable $start): void
    {
        $hours = (int) ($quote->item['hours'] ?? 0);
        $quote->units = $hours;
        $startTime = $this->parseTime($quote->item['start_time'] ?? null);

        if (! $calendar->canStartOn($start)) {
            $quote->fail('start_date', $calendar->isInSeason($start)
                ? __('The school is closed on this day.')
                : __('The school is not open on this day (outside the season).'));
        }

        if ($startTime === null) {
            $quote->fail('start_time', __('Please enter a start time.'));

            return;
        }

        if ($hours < 1 || $hours > (int) $table->max_hours) {
            $quote->fail('hours', __('Please choose between 1 and :max hours.', ['max' => $table->max_hours]));

            return;
        }

        $endTime = $startTime + $hours * 60;
        $window = $school->timeslot(TimeslotKey::PrivateDay);
        if ($window && ($startTime < $this->minutes($window->starts_at) || $endTime > $this->minutes($window->ends_at))) {
            $quote->fail('start_time', __('Private lessons take place between :from and :to.', [
                'from' => $window->startsAtLabel(), 'to' => $window->endsAtLabel(),
            ]));

            return;
        }

        // Plan question 8: the time option follows from start and length.
        $quote->option = $this->optionFor($school, $startTime, $endTime);
        if (! in_array($quote->option, $table->activeTimeslotKeys(), true)) {
            $quote->fail('start_time', __('This course is not offered as :option.', ['option' => $quote->option->label()]));
            $quote->option = null;

            return;
        }

        $min = $table->minHoursFor($quote->option) ?? 1;
        if ($hours < $min) {
            $quote->fail('hours', __('At least :count hours must be booked.', ['count' => $min]));

            return;
        }

        $quote->startTime = $this->formatMinutes($startTime);
        $quote->endTime = $this->formatMinutes($endTime);
        $quote->lessonDates = $calendar->canStartOn($start) ? collect([$start]) : collect();
    }

    private function checkParticipants(Quote $quote, PriceTable $table, ?CarbonImmutable $start): void
    {
        $wanted = collect($quote->item['students'] ?? [])
            ->mapWithKeys(fn ($s) => [(int) $s['id'] => (bool) ($s['age_override'] ?? false)]);

        if ($wanted->isEmpty()) {
            $quote->fail('students', __('Please add at least one participant.'));

            return;
        }

        $students = Student::query()->whereKey($wanted->keys())->get()->keyBy('id');
        $levelIds = $table->skillLevels->pluck('id')->all();
        $day = $start ?? CarbonImmutable::today();

        foreach ($wanted as $id => $override) {
            $student = $students->get($id);
            if (! $student || $student->isArchived()) {
                $quote->fail("students.{$id}", __('This student cannot be booked.'));

                continue;
            }

            // US-10 / US-11: the skill level must match. This is a hard rule.
            if (! in_array($student->skill_level_id, $levelIds, true)) {
                $quote->fail("students.{$id}", __(':name has skill level :level, which this course is not for.', [
                    'name' => $student->fullName(), 'level' => $student->skillLevel?->name,
                ]));

                continue;
            }

            // Plan decision: the age group is a guideline. Outside it, the
            // office has to confirm once ("book anyway").
            $age = $student->ageOn($day);
            $fits = $table->ageGroups->contains(fn ($group) => $group->includesAge($age));
            if (! $fits && ! $override) {
                $quote->ageWarnings[$id] = __(':name is :age, outside the age groups of this course (:groups).', [
                    'name' => $student->fullName(), 'age' => $age,
                    'groups' => $table->ageGroups->map(fn ($g) => "{$g->name} {$g->min_age}–{$g->max_age}")->implode(', '),
                ]);
            }

            $quote->participants[$id] = ['student' => $student, 'age' => $age, 'age_override' => ! $fits];
        }
    }

    /** The latest day this course may run: the next price-table end weekday. */
    public function latestEnd(PriceTable $table, CarbonImmutable $start): ?CarbonImmutable
    {
        if ($table->end_weekday === null) {
            return null;
        }

        return $start->addDays((($table->end_weekday - $start->dayOfWeek) % 7 + 7) % 7);
    }

    /** Plan question 8: before the afternoon slot = morning, covering both = full day. */
    public function optionFor(School $school, int $start, int $end): TimeslotKey
    {
        $morning = $school->timeslot(TimeslotKey::Morning);
        $afternoon = $school->timeslot(TimeslotKey::Afternoon);
        $afternoonStart = $afternoon ? $this->minutes($afternoon->starts_at) : 13 * 60;
        $morningEnd = $morning ? $this->minutes($morning->ends_at) : 12 * 60;

        if ($start < $morningEnd && $end > $afternoonStart) {
            return TimeslotKey::FullDay;
        }

        return $start < $afternoonStart ? TimeslotKey::Morning : TimeslotKey::Afternoon;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseTime(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
