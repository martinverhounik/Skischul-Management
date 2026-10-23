<?php

namespace App\Domain\Booking;

use App\Models\Booking;

/** Turns a stored booking back into cart items, for editing it (US-16). */
class BookingItems
{
    /** @return list<array> */
    public static function fromBooking(Booking $booking): array
    {
        $booking->loadMissing('lines.students');

        return $booking->lines->map(function ($line) {
            $hourly = $line->course_type->isHourly();

            return [
                'price_table_id' => $line->price_table_id,
                'sport_id' => $line->sport_id,
                'timeslot_key' => $line->timeslot_key->value,
                'start_date' => $line->start_date->toDateString(),
                'days' => $hourly ? 0 : $line->units,
                'start_time' => $hourly ? substr((string) $line->start_time, 0, 5) : '',
                'hours' => $hourly ? $line->units : 0,
                'students' => $line->students->map(fn ($s) => [
                    'id' => $s->id,
                    'age_override' => (bool) $s->pivot->age_override,
                ])->all(),
            ];
        })->all();
    }

    /** @return array<int, int> add_on_id => quantity */
    public static function addOns(Booking $booking): array
    {
        return $booking->addOns()->pluck('quantity', 'add_on_id')->map(fn ($q) => (int) $q)->all();
    }
}
