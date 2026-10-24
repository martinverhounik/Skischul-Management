<?php

namespace App\Domain\Booking;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\GroupMember;
use App\Models\LessonDay;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

/**
 * Paid / unpaid, handled, cancel and delete (US-15, US-16). Every change is
 * written to the booking's history with the user who made it.
 */
class ChangeBookingStatus
{
    public function setPaid(Booking $booking, bool $paid, User $user): void
    {
        $booking->forceFill([
            'payment_status' => $paid ? PaymentStatus::Paid : PaymentStatus::Unpaid,
            'paid_at' => $paid ? now() : null,
        ])->save();

        $booking->record($paid ? 'paid' : 'unpaid', $user);
    }

    /** Handled bookings move to the second table (US-14, US-15). */
    public function setHandled(Booking $booking, bool $handled, User $user): void
    {
        $booking->forceFill(['handled_at' => $handled ? now() : null])->save();

        $booking->record($handled ? 'handled' : 'unhandled', $user);
    }

    /**
     * Cancelling keeps the booking, clearly marked. It no longer counts for
     * the dashboard or for grouping, and a voucher use is given back.
     */
    public function cancel(Booking $booking, User $user, ?string $reason = null): void
    {
        if ($booking->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($booking, $user, $reason) {
            $booking->forceFill(['cancelled_at' => now()])->save();
            $this->releaseVoucher($booking);

            // US-16: the participants leave their groups and the private
            // lessons lose their instructor, so planning figures stay right.
            $lessonDayIds = LessonDay::query()->whereIn('booking_line_id', $booking->lines()->pluck('id'))->pluck('id');
            GroupMember::query()->whereIn('lesson_day_id', $lessonDayIds)->delete();
            LessonDay::query()->whereKey($lessonDayIds)->update(['instructor_id' => null]);
            $booking->record('cancelled', $user, $reason ? ['reason' => $reason] : []);
        });
    }

    /** Permanent delete, distinct from cancel. Owners only (see BookingPolicy). */
    public function delete(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            if (! $booking->isCancelled()) {
                $this->releaseVoucher($booking);
            }
            $booking->delete(); // lines, participants, lesson days, extras, history cascade
        });
    }

    private function releaseVoucher(Booking $booking): void
    {
        if ($booking->voucher_id) {
            Voucher::query()->whereKey($booking->voucher_id)->where('times_used', '>', 0)->decrement('times_used');
        }
    }
}
