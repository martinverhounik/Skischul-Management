<?php

namespace App\Domain\Booking;

use App\Models\AddOn;
use App\Models\Booking;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The parts shared by placing a booking (US-13) and changing one (US-16):
 * check every course again, add up the price, write the courses,
 * participants, lesson days and extras.
 */
class BookingWriter
{
    public function __construct(private BookingRules $rules) {}

    /**
     * @param  list<array>  $items
     * @return list<Quote>
     *
     * @throws ValidationException naming the first course that is not valid
     */
    public function checkAll(School $school, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['cart' => __('The cart is empty.')]);
        }

        $quotes = [];
        foreach (array_values($items) as $i => $item) {
            $quote = $this->rules->check($school, $item);
            if (! $quote->isValid()) {
                $message = collect($quote->errors)->first() ?? collect($quote->ageWarnings)->first();
                throw ValidationException::withMessages(['cart' => __('Course :n: :message', ['n' => $i + 1, 'message' => $message])]);
            }
            $quotes[] = $quote;
        }

        return $quotes;
    }

    /** @return Collection<int, AddOn> */
    public function activeAddOns(array $addOns): Collection
    {
        return AddOn::query()->whereKey(array_keys($addOns))->where('is_active', true)->get();
    }

    public function subtotal(array $quotes, Collection $extras, array $addOns): int
    {
        return array_sum(array_map(fn (Quote $q) => $q->priceCents, $quotes))
            + $extras->sum(fn (AddOn $a) => $a->price_cents * (int) $addOns[$a->id]);
    }

    /** Writes the courses and extras of a booking, replacing any existing ones. */
    public function writeContent(Booking $booking, School $school, array $quotes, Collection $extras, array $addOns): void
    {
        // Lesson days and participants go with their lines (cascade).
        $booking->lines()->get()->each->delete();
        $booking->addOns()->delete();

        foreach ($quotes as $quote) {
            $line = $booking->lines()->create([
                'school_id' => $school->id,
                'price_table_id' => $quote->priceTable->id,
                'sport_id' => $quote->sport->id,
                'course_type' => $quote->priceTable->course_type,
                'timeslot_key' => $quote->option,
                'start_date' => $quote->startDate(),
                'end_date' => $quote->endDate(),
                'units' => $quote->units,
                'lesson_count' => $quote->lessonDates->count(),
                'start_time' => $quote->startTime,
                'price_cents' => $quote->priceCents,
            ]);

            foreach ($quote->participants as $id => $p) {
                $line->students()->attach($id, ['age_at_start' => $p['age'], 'age_override' => $p['age_override']]);
            }

            foreach ($quote->lessonDates as $date) {
                $line->lessonDays()->create([
                    'school_id' => $school->id,
                    'date' => $date->toDateString(),
                    'timeslot_key' => $quote->option,
                    'starts_at' => $quote->startTime,
                    'ends_at' => $quote->endTime,
                ]);
            }
        }

        foreach ($extras as $addOn) {
            $quantity = (int) $addOns[$addOn->id];
            $booking->addOns()->create([
                'add_on_id' => $addOn->id,
                'quantity' => $quantity,
                'unit_price_cents' => $addOn->price_cents,
                'total_cents' => $addOn->price_cents * $quantity,
            ]);
        }
    }
}
