<?php

namespace App\Domain\Booking;

use App\Enums\PaymentMethod;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\School;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\BookingConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Checkout (US-13): checks everything once more, then creates the booking
 * with its courses, participants, lesson days, extras and voucher use in
 * one transaction. The confirmation email (US-29) is sent afterwards; if it
 * fails, the booking is kept and the failure is recorded.
 */
class PlaceOrder
{
    public function __construct(private BookingRules $rules) {}

    /**
     * @param  list<array>  $items  cart items
     * @param  array<int, int>  $addOns  add_on_id => quantity
     * @param  array  $booker  booker_name, email, phone, street, house_number, postal_code, city, country, payment_method, notes
     */
    public function handle(School $school, User $user, array $items, array $addOns, array $booker, ?string $voucherCode): Booking
    {
        if ($items === []) {
            throw ValidationException::withMessages(['cart' => __('The cart is empty.')]);
        }

        $booking = DB::transaction(function () use ($school, $user, $items, $addOns, $booker, $voucherCode) {
            // Serialises orders of one school, for booking numbers and voucher limits.
            School::query()->whereKey($school->id)->lockForUpdate()->first();

            $quotes = [];
            foreach (array_values($items) as $i => $item) {
                $quote = $this->rules->check($school, $item);
                if (! $quote->isValid()) {
                    $message = collect($quote->errors)->first() ?? collect($quote->ageWarnings)->first();
                    throw ValidationException::withMessages(['cart' => __('Course :n: :message', ['n' => $i + 1, 'message' => $message])]);
                }
                $quotes[] = $quote;
            }

            $extras = AddOn::query()->whereKey(array_keys($addOns))->where('is_active', true)->get();
            $subtotal = array_sum(array_map(fn (Quote $q) => $q->priceCents, $quotes))
                + $extras->sum(fn (AddOn $a) => $a->price_cents * (int) $addOns[$a->id]);

            $voucher = null;
            $discount = 0;
            if ($voucherCode !== null && trim($voucherCode) !== '') {
                $voucher = Voucher::query()->where('code', mb_strtoupper(trim($voucherCode)))->lockForUpdate()->first();
                $reason = $voucher ? $voucher->rejectionReason($school->today()) : __('This voucher code does not exist.');
                if ($reason) {
                    throw ValidationException::withMessages(['voucher' => $reason]);
                }
                $discount = $voucher->discountFor($subtotal);
                $voucher->increment('times_used');
            }

            $booking = new Booking([
                'booker_name' => $booker['booker_name'],
                'email' => mb_strtolower($booker['email']),
                'phone' => $booker['phone'] ?: null,
                'street' => $booker['street'],
                'house_number' => $booker['house_number'],
                'postal_code' => $booker['postal_code'],
                'city' => $booker['city'],
                'country' => strtoupper($booker['country']),
                'payment_method' => PaymentMethod::from($booker['payment_method']),
                'notes' => $booker['notes'] ?? null ?: null,
            ]);
            $booking->forceFill([
                'school_id' => $school->id,
                'number' => $this->nextNumber($school),
                'voucher_id' => $voucher?->id,
                'voucher_code' => $voucher?->code,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'total_cents' => $subtotal - $discount,
                'created_by' => $user->id,
            ])->save();

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

            $booking->record('created', $user, ['total_cents' => $booking->total_cents]);

            return $booking;
        });

        $this->sendConfirmation($booking, $school, $user);

        return $booking;
    }

    /** US-29: a failing email never loses the booking. */
    public function sendConfirmation(Booking $booking, School $school, ?User $user = null): bool
    {
        try {
            Notification::route('mail', [$booking->email => $booking->booker_name])
                ->notify((new BookingConfirmation($booking->fresh()))->locale($school->locale));
            $booking->forceFill(['confirmation_sent_at' => now()])->save();
            $booking->record('email_sent', $user);

            return true;
        } catch (Throwable $e) {
            Log::warning('Booking confirmation email failed', ['booking' => $booking->id, 'error' => $e->getMessage()]);
            $booking->record('email_failed', $user, ['error' => mb_substr($e->getMessage(), 0, 250)]);

            return false;
        }
    }

    private function nextNumber(School $school): string
    {
        $year = $school->today()->year;
        $last = Booking::query()
            ->where('school_id', $school->id)
            ->where('number', 'like', "{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $next = $last ? ((int) substr($last, 5)) + 1 : 1;

        return sprintf('%d-%04d', $year, $next);
    }
}
