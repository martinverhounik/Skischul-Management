<?php

namespace App\Livewire\Bookings;

use App\Domain\Booking\PlaceOrder;
use App\Livewire\Concerns\ManagesBookings;
use App\Models\Booking;
use App\Support\CurrentSchool;
use Livewire\Component;

/** One booking with everything in it, and its actions (US-15, US-16). */
class Show extends Component
{
    use ManagesBookings;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        // The link is already resolved inside the user's school; this is a
        // second, independent check.
        $this->authorize('update', $booking);
        $this->booking = $booking;
    }

    protected function afterBookingChange(): void
    {
        $this->booking->refresh();
    }

    public function resendConfirmation(PlaceOrder $placeOrder): void
    {
        $sent = $placeOrder->sendConfirmation($this->booking, app(CurrentSchool::class)->get(), auth()->user());
        $this->status = $sent ? __('The confirmation was sent to :email.', ['email' => $this->booking->email]) : __('The email could not be sent. Please check the address.');
        $this->booking->refresh();
    }

    public function render()
    {
        $this->booking->load('lines.priceTable.sports', 'lines.sport', 'lines.students.skillLevel', 'lines.lessonDays', 'addOns.addOn', 'events.user', 'creator');

        return view('livewire.bookings.show')->title(__('Booking :number', ['number' => $this->booking->number]));
    }
}
