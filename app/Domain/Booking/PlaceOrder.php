<?php

namespace App\Domain\Booking;

use App\Enums\PaymentMethod;
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
    public function __construct(private BookingWriter $writer) {}

    /**
     * @param  list<array>  $items  cart items
     * @param  array<int, int>  $addOns  add_on_id => quantity
     * @param  array  $booker  booker_name, email, phone, street, house_number, postal_code, city, country, payment_method, notes
     */
    public function handle(School $school, User $user, array $items, array $addOns, array $booker, ?string $voucherCode): Booking
    {
        $booking = DB::transaction(function () use ($school, $user, $items, $addOns, $booker, $voucherCode) {
            // Serialises orders of one school, for booking numbers and voucher limits.
            School::query()->whereKey($school->id)->lockForUpdate()->first();

            $quotes = $this->writer->checkAll($school, $items);
            $extras = $this->writer->activeAddOns($addOns);
            $subtotal = $this->writer->subtotal($quotes, $extras, $addOns);

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

            $booking = new Booking(self::bookerAttributes($booker));
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

            $this->writer->writeContent($booking, $school, $quotes, $extras, $addOns);
            $booking->record('created', $user, ['total_cents' => $booking->total_cents]);

            return $booking;
        });

        $this->sendConfirmation($booking, $school, $user);

        return $booking;
    }

    /** The booker / billing fields as stored on the booking. */
    public static function bookerAttributes(array $booker): array
    {
        return [
            'booker_name' => $booker['booker_name'],
            'email' => mb_strtolower($booker['email']),
            'phone' => ($booker['phone'] ?? '') ?: null,
            'street' => $booker['street'],
            'house_number' => $booker['house_number'],
            'postal_code' => $booker['postal_code'],
            'city' => $booker['city'],
            'country' => strtoupper($booker['country']),
            'payment_method' => PaymentMethod::from($booker['payment_method']),
            'notes' => ($booker['notes'] ?? '') ?: null,
        ];
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
