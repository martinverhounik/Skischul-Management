<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Staff do the day-to-day work on bookings; deleting a booking for good is
 * left to owners (plan, section 4). Cancelling is open to everyone.
 */
class BookingPolicy
{
    public function update(User $user, Booking $booking): bool
    {
        return $user->school_id === $booking->school_id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->isOwner() && $user->school_id === $booking->school_id;
    }
}
