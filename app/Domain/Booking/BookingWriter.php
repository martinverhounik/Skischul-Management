<?php

namespace App\Domain\Booking;

use App\Models\AddOn;
use App\Models\Booking;
use App\Models\GroupMember;
use App\Models\LessonDay;
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

    /** @return array{0: array<string, int>, 1: array<string, array>} */
    private function rememberAssignments(Booking $booking): array
    {
        $days = LessonDay::query()->with('line', 'groupMembers')
            ->whereIn('booking_line_id', $booking->lines()->pluck('id'))->get();

        $groupPlaces = [];
        $private = [];
        foreach ($days as $day) {
            foreach ($day->groupMembers as $member) {
                $groupPlaces["{$member->student_id}|{$day->date->toDateString()}|{$day->timeslot_key->value}|{$day->line->sport_id}"] = $member->group_id;
            }
            if ($day->instructor_id || $day->meeting_point_id) {
                $private["{$day->date->toDateString()}|".substr((string) $day->starts_at, 0, 5)."|{$day->line->sport_id}"] = [
                    'instructor_id' => $day->instructor_id,
                    'meeting_point_id' => $day->meeting_point_id,
                ];
            }
        }

        return [$groupPlaces, $private];
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

    /**
     * Writes the courses and extras of a booking, replacing any existing ones.
     * When a booking is changed, students keep their group on every lesson
     * day that still exists, and private lessons keep their instructor and
     * meeting point if date, time and sport are unchanged (step 7).
     */
    public function writeContent(Booking $booking, School $school, array $quotes, Collection $extras, array $addOns): void
    {
        [$groupPlaces, $privateAssignments] = $this->rememberAssignments($booking);

        // Lesson days, participants and group places go with their lines (cascade).
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
                $privateKey = "{$date->toDateString()}|{$quote->startTime}|{$quote->sport->id}";
                $lessonDay = $line->lessonDays()->create([
                    'school_id' => $school->id,
                    'date' => $date->toDateString(),
                    'timeslot_key' => $quote->option,
                    'starts_at' => $quote->startTime,
                    'ends_at' => $quote->endTime,
                    'instructor_id' => $privateAssignments[$privateKey]['instructor_id'] ?? null,
                    'meeting_point_id' => $privateAssignments[$privateKey]['meeting_point_id'] ?? null,
                ]);

                foreach (array_keys($quote->participants) as $studentId) {
                    $groupId = $groupPlaces["{$studentId}|{$date->toDateString()}|{$quote->option->value}|{$quote->sport->id}"] ?? null;
                    if ($groupId) {
                        GroupMember::query()->create(['group_id' => $groupId, 'lesson_day_id' => $lessonDay->id, 'student_id' => $studentId]);
                    }
                }
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
