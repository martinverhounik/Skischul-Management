<?php

namespace App\Domain\Pricing;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\PriceTable;

/**
 * Works out the price of a course from its price table (US-10, US-11).
 *
 * Group courses: the price per participant for the number of LESSON days,
 * times the number of participants (plan question 6 and the closed-day
 * decision: only days with a lesson are charged). Closed days are skipped
 * as if they did not exist, so this normally is the booked number of days;
 * it is less only when the course reaches its latest end day first. If that
 * leaves fewer days than the table's shortest row, the shortest row's day
 * rate is used for each lesson day.
 *
 * Private lessons / guiding: the base price for the booked hours plus the
 * additional-participant fee from the same row for each further participant.
 */
class PriceCalculator
{
    public function quote(PriceTable $table, TimeslotKey $option, int $units, int $participants): int
    {
        $table->loadMissing('prices', 'timeOptions');

        if ($participants < 1) {
            throw new PricingException(__('At least one participant is needed.'));
        }

        if (! in_array($option, $table->activeTimeslotKeys(), true)) {
            throw new PricingException(__('This time option is not offered for this course.'));
        }

        return $table->course_type === CourseType::Group
            ? $this->groupPrice($table, $option, $units, $participants)
            : $this->hourlyPrice($table, $option, $units, $participants);
    }

    private function groupPrice(PriceTable $table, TimeslotKey $option, int $lessonDays, int $participants): int
    {
        if ($lessonDays < 1) {
            throw new PricingException(__('A course needs at least one day.'));
        }

        $cell = $table->priceCell($option, $lessonDays);

        if ($cell) {
            return $cell->price_cents * $participants;
        }

        $shortest = $table->prices->where('timeslot_key', $option)->sortBy('units')->first();
        if ($shortest && $lessonDays < $shortest->units) {
            $perDay = $shortest->price_cents / $shortest->units;

            return (int) round($perDay * $lessonDays) * $participants;
        }

        throw new PricingException(__('There is no price for :count days.', ['count' => $lessonDays]));
    }

    private function hourlyPrice(PriceTable $table, TimeslotKey $option, int $hours, int $participants): int
    {
        $min = $table->minHoursFor($option) ?? 1;
        if ($hours < $min) {
            throw new PricingException(__('At least :count hours must be booked.', ['count' => $min]));
        }

        $cell = $table->priceCell($option, $hours);
        if (! $cell) {
            throw new PricingException(__('There is no price for :count hours.', ['count' => $hours]));
        }

        return $cell->price_cents + ($participants - 1) * (int) $cell->extra_participant_cents;
    }
}
