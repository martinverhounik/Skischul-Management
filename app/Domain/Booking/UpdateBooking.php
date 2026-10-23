<?php

namespace App\Domain\Booking;

use App\Models\Booking;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes an existing booking (US-16): participants, dates, courses,
 * extras and booker details. Every course is checked against the rules
 * again. A voucher stays on the booking and its discount is recalculated;
 * it is not counted as used a second time.
 */
class UpdateBooking
{
    public function __construct(private BookingWriter $writer) {}

    public function handle(Booking $booking, School $school, User $user, array $items, array $addOns, array $booker): Booking
    {
        if ($booking->isCancelled()) {
            throw ValidationException::withMessages(['cart' => __('A cancelled booking cannot be changed.')]);
        }

        return DB::transaction(function () use ($booking, $school, $user, $items, $addOns, $booker) {
            $before = $booking->total_cents;

            $quotes = $this->writer->checkAll($school, $items);
            $extras = $this->writer->activeAddOns($addOns);
            $subtotal = $this->writer->subtotal($quotes, $extras, $addOns);
            $discount = $booking->voucher ? $booking->voucher->discountFor($subtotal) : 0;

            $booking->fill(PlaceOrder::bookerAttributes($booker));
            $booking->forceFill([
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'total_cents' => $subtotal - $discount,
            ])->save();

            $this->writer->writeContent($booking, $school, $quotes, $extras, $addOns);
            $booking->record('edited', $user, ['total_before_cents' => $before, 'total_after_cents' => $booking->total_cents]);

            return $booking->fresh();
        });
    }
}
