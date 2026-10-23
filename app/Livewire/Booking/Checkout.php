<?php

namespace App\Livewire\Booking;

use App\Domain\Booking\Cart;
use App\Domain\Booking\PlaceOrder;
use App\Domain\Booking\UpdateBooking;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Voucher;
use App\Support\CurrentSchool;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Checkout (US-13): booker and billing details, payment method, voucher,
 * summary, place the order.
 */
class Checkout extends Component
{
    public const COUNTRIES = ['AT', 'DE', 'CH', 'IT', 'NL', 'BE', 'LU', 'DK', 'SE', 'NO', 'GB', 'IE', 'FR', 'CZ', 'SK', 'PL', 'HU', 'SI', 'HR', 'US'];

    public string $booker_name = '';

    public string $email = '';

    public string $phone = '';

    public string $street = '';

    public string $house_number = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country = 'AT';

    public string $payment_method = 'cash';

    public string $notes = '';

    public string $voucherInput = '';

    /** The voucher that was accepted, if any. */
    public ?string $voucherCode = null;

    /** Set when an existing booking is being changed (US-16). */
    #[Url(as: 'booking', except: null)]
    public ?int $editBooking = null;

    /** Send the booker an updated confirmation after a change. */
    public bool $sendUpdate = true;

    public function mount(): void
    {
        if ($this->editBooking !== null) {
            $booking = $this->editedBooking();
            $this->fill($booking->only('booker_name', 'email', 'street', 'house_number', 'postal_code', 'city', 'country'));
            $this->phone = $booking->phone ?? '';
            $this->notes = $booking->notes ?? '';
            $this->payment_method = $booking->payment_method->value;
            $this->voucherCode = $booking->voucher_code;
        }

        if ($this->cart()->isEmpty($this->school())) {
            $this->redirectRoute('booking', $this->editBooking ? ['booking' => $this->editBooking] : [], navigate: true);
        }
    }

    private function editedBooking(): Booking
    {
        $booking = Booking::query()->findOrFail($this->editBooking);
        $this->authorize('update', $booking);
        abort_if($booking->isCancelled(), 404);

        return $booking;
    }

    private function school()
    {
        return app(CurrentSchool::class)->get();
    }

    private function cart(): Cart
    {
        return app(Cart::class)->forBooking($this->editBooking);
    }

    /** US-13 / US-28: only an active, unexpired voucher is accepted. */
    public function applyVoucher(): void
    {
        if ($this->editBooking !== null) {
            return; // the voucher of an existing booking stays as it is
        }
        $this->resetErrorBag('voucherInput');
        $code = mb_strtoupper(trim($this->voucherInput));
        if ($code === '') {
            return;
        }

        $voucher = Voucher::query()->where('code', $code)->first();
        $reason = $voucher ? $voucher->rejectionReason($this->school()->today()) : __('This voucher code does not exist.');

        if ($reason) {
            $this->voucherCode = null;
            $this->addError('voucherInput', $reason);

            return;
        }

        $this->voucherCode = $voucher->code;
        $this->voucherInput = '';
    }

    public function removeVoucher(): void
    {
        $this->voucherCode = null;
    }

    /** Fill the booker name from a participant (US-13: "same as a student"). */
    public function useParticipant(string $name): void
    {
        $this->booker_name = $name;
    }

    public function placeOrder(PlaceOrder $placeOrder): void
    {
        $data = $this->validate([
            'booker_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\/\s.-]{5,}$/'],
            'street' => ['required', 'string', 'max:255'],
            'house_number' => ['required', 'string', 'max:20'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', Rule::in(self::COUNTRIES)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'booker_name' => __('Name of the booker'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'street' => __('Street'),
            'house_number' => __('House number'),
            'postal_code' => __('Postal code'),
            'city' => __('City'),
            'country' => __('Country'),
            'payment_method' => __('Payment method'),
        ]);

        $school = $this->school();

        if ($this->editBooking !== null) {
            $booking = app(UpdateBooking::class)->handle(
                $this->editedBooking(), $school, auth()->user(),
                array_values($this->cart()->items($school)), $this->cart()->addOns($school), $data,
            );
            $this->cart()->clear($school);
            if ($this->sendUpdate) {
                $placeOrder->sendConfirmation($booking, $school, auth()->user());
            }
            session()->flash('status', __('Booking :number has been changed.', ['number' => $booking->number]));
            $this->redirectRoute('bookings.show', $booking, navigate: true);

            return;
        }

        try {
            $booking = $placeOrder->handle(
                $school,
                auth()->user(),
                array_values($this->cart()->items($school)),
                $this->cart()->addOns($school),
                $data,
                $this->voucherCode,
            );
        } catch (ValidationException $e) {
            if (isset($e->errors()['voucher'])) {
                $this->voucherCode = null;
                $this->addError('voucherInput', $e->errors()['voucher'][0]);

                return;
            }
            throw $e;
        }

        $this->cart()->clear($school);

        session()->flash('status', $booking->confirmation_sent_at
            ? __('Booking :number has been placed. The confirmation was sent to :email.', ['number' => $booking->number, 'email' => $booking->email])
            : __('Booking :number has been placed, but the confirmation email could not be sent.', ['number' => $booking->number]));

        $this->redirectRoute('bookings.show', $booking, navigate: true);
    }

    public function render()
    {
        $school = $this->school();
        $quotes = $this->cart()->quotes($school);
        $addOnLines = $this->cart()->addOnLines($school);
        $subtotal = $this->cart()->subtotal($school);
        $voucher = $this->editBooking
            ? $this->editedBooking()->voucher
            : ($this->voucherCode ? Voucher::query()->where('code', $this->voucherCode)->first() : null);
        $discount = $voucher ? $voucher->discountFor($subtotal) : 0;

        $participants = collect($quotes)->flatMap(fn ($q) => collect($q->participants)->map(fn ($p) => $p['student']->fullName()))->unique()->values();

        return view('livewire.booking.checkout', [
            'editedBooking' => $this->editBooking ? $this->editedBooking() : null,
            'quotes' => $quotes,
            'addOnLines' => $addOnLines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'allValid' => collect($quotes)->every->isValid(),
            'participants' => $participants,
            'countries' => collect(self::COUNTRIES)->mapWithKeys(fn ($c) => [$c => \Locale::getDisplayRegion('-'.$c, app()->getLocale()).' ('.$c.')'])->all(),
        ])->title(__('Checkout'));
    }
}
