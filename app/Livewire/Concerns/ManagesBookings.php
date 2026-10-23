<?php

namespace App\Livewire\Concerns;

use App\Domain\Booking\BookingItems;
use App\Domain\Booking\Cart;
use App\Domain\Booking\ChangeBookingStatus;
use App\Models\Booking;
use App\Support\CurrentSchool;

/**
 * Booking actions shared by the list and the booking page (US-15, US-16).
 * Ids come from the browser, so each one is looked up inside the school
 * and checked against BookingPolicy.
 */
trait ManagesBookings
{
    public ?string $status = null;

    protected function findBooking(int $id): Booking
    {
        $booking = Booking::query()->findOrFail($id);
        $this->authorize('update', $booking);

        return $booking;
    }

    public function togglePaid(int $id): void
    {
        $booking = $this->findBooking($id);
        app(ChangeBookingStatus::class)->setPaid($booking, ! $booking->isPaid(), auth()->user());
        $this->status = $booking->isPaid()
            ? __(':number is marked as paid.', ['number' => $booking->number])
            : __(':number is marked as unpaid.', ['number' => $booking->number]);
        $this->afterBookingChange();
    }

    public function toggleHandled(int $id): void
    {
        $booking = $this->findBooking($id);
        app(ChangeBookingStatus::class)->setHandled($booking, ! $booking->isHandled(), auth()->user());
        $this->status = $booking->isHandled()
            ? __(':number is handled.', ['number' => $booking->number])
            : __(':number is active again.', ['number' => $booking->number]);
        $this->afterBookingChange();
    }

    public function cancelBooking(int $id): void
    {
        $booking = $this->findBooking($id);
        app(ChangeBookingStatus::class)->cancel($booking, auth()->user());
        $this->status = __(':number has been cancelled.', ['number' => $booking->number]);
        $this->afterBookingChange();
    }

    public function deleteBooking(int $id): void
    {
        $booking = $this->findBooking($id);
        $this->authorize('delete', $booking);
        $number = $booking->number;

        app(ChangeBookingStatus::class)->delete($booking);

        session()->flash('status', __(':number has been deleted.', ['number' => $number]));
        $this->redirectRoute('bookings.index', navigate: true);
    }

    /** US-16: open the booking in the booking screen, in its own cart. */
    public function editBooking(int $id): void
    {
        $booking = $this->findBooking($id);
        abort_if($booking->isCancelled(), 422);

        app(Cart::class)->forBooking($booking->id)->fill(
            app(CurrentSchool::class)->get(),
            BookingItems::fromBooking($booking),
            BookingItems::addOns($booking),
        );

        $this->redirectRoute('booking', ['booking' => $booking->id], navigate: true);
    }

    protected function afterBookingChange(): void {}
}
