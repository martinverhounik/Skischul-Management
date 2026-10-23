<?php

use App\Domain\Booking\BookingItems;
use App\Domain\Booking\Cart;
use App\Domain\Booking\PlaceOrder;
use App\Livewire\Booking\Checkout;
use App\Livewire\Booking\Shop;
use App\Livewire\Bookings\Index;
use App\Livewire\Bookings\Show;
use App\Models\Booking;
use App\Models\LessonDay;
use App\Models\User;
use App\Models\Voucher;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

require_once __DIR__.'/BookingSetup.php';

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-12-01 09:00:00');
    $this->f = bookingSchool();
    $this->staff = User::factory()->forSchool($this->f['school'])->create();

    $this->book = function (array $items, string $name = 'Petra Brandner', ?string $voucher = null): Booking {
        return app(PlaceOrder::class)->handle($this->f['school'], $this->f['owner'], $items, [], [
            'booker_name' => $name, 'email' => 'p@example.com', 'phone' => '', 'street' => 'Weg', 'house_number' => '1',
            'postal_code' => '6020', 'city' => 'Innsbruck', 'country' => 'AT', 'payment_method' => 'cash',
        ], $voucher);
    };
});

describe('the list (US-14)', function () {
    it('lists active bookings by booker with their students underneath', function () {
        ($this->book)([groupItem($this->f, ['students' => [['id' => $this->f['lisa']->id], ['id' => $this->f['tom']->id]]])]);

        Livewire::test(Index::class)
            ->assertSee('Petra Brandner')->assertSee('Lisa Brandner')->assertSee('Tom Brandner')
            ->assertSee('Kinderskikurs')->assertSee('500,00')->assertSee(__('Unpaid'));
    });

    it('shows handled and cancelled bookings in the second table', function () {
        $handled = ($this->book)([groupItem($this->f)], 'Erika Erledigt');
        ($this->book)([privateItem($this->f)], 'Anton Aktiv');
        $handled->forceFill(['handled_at' => now()])->save();

        $c = Livewire::test(Index::class);
        expect($c->viewData('active')->pluck('booker_name')->all())->toBe(['Anton Aktiv'])
            ->and($c->viewData('done')->pluck('booker_name')->all())->toBe(['Erika Erledigt']);
    });

    it('searches by booker, student and number', function () {
        ($this->book)([groupItem($this->f)], 'Petra Brandner');
        ($this->book)([privateItem($this->f, ['students' => [['id' => $this->f['tom']->id]]])], 'Karl Klein');

        expect(Livewire::test(Index::class)->set('search', 'klein')->viewData('active')->pluck('booker_name')->all())->toBe(['Karl Klein'])
            ->and(Livewire::test(Index::class)->set('search', 'Lisa')->viewData('active')->pluck('booker_name')->all())->toBe(['Petra Brandner'])
            ->and(Livewire::test(Index::class)->set('search', '2026-0002')->viewData('active')->pluck('booker_name')->all())->toBe(['Karl Klein']);
    });
});

describe('payment and handled (US-15)', function () {
    it('marks a booking paid and unpaid, and records who did it', function () {
        $booking = ($this->book)([groupItem($this->f)]);
        $this->signIn($this->staff);

        Livewire::test(Index::class)->call('togglePaid', $booking->id);
        expect($booking->fresh())->isPaid()->toBeTrue()->paid_at->not->toBeNull()
            ->and($booking->events()->where('action', 'paid')->value('user_id'))->toBe($this->staff->id);

        Livewire::test(Index::class)->call('togglePaid', $booking->id);
        expect($booking->fresh()->isPaid())->toBeFalse();
    });

    it('moves a booking to handled and back', function () {
        $booking = ($this->book)([groupItem($this->f)]);

        Livewire::test(Index::class)->call('toggleHandled', $booking->id);
        expect($booking->fresh()->isHandled())->toBeTrue();

        Livewire::test(Show::class, ['booking' => $booking->fresh()])->call('toggleHandled', $booking->id);
        expect($booking->fresh()->isHandled())->toBeFalse();
    });
});

describe('cancel and delete (US-16)', function () {
    it('cancels: keeps the booking, marks it and gives the voucher use back', function () {
        $voucher = Voucher::create(['code' => 'ONCE', 'discount_type' => 'fixed', 'discount_value' => 1000, 'max_uses' => 1]);
        $booking = ($this->book)([groupItem($this->f)], 'Petra Brandner', 'ONCE');
        expect($voucher->fresh()->times_used)->toBe(1);

        Livewire::test(Index::class)->call('cancelBooking', $booking->id);

        expect($booking->fresh()->isCancelled())->toBeTrue()
            ->and(Booking::count())->toBe(1)
            ->and($voucher->fresh()->times_used)->toBe(0)
            ->and(Livewire::test(Index::class)->viewData('done')->pluck('id')->all())->toBe([$booking->id]);
    });

    it('lets only owners delete for good, with everything in it', function () {
        $booking = ($this->book)([groupItem($this->f)]);

        $this->signIn($this->staff);
        Livewire::test(Index::class)->call('deleteBooking', $booking->id)->assertForbidden();
        expect(Booking::count())->toBe(1);

        $this->signIn($this->f['owner']);
        Livewire::test(Index::class)->call('deleteBooking', $booking->id)->assertRedirect(route('bookings.index'));
        expect(Booking::count())->toBe(0)->and(LessonDay::count())->toBe(0);
    });

    it('hides the delete button from staff', function () {
        ($this->book)([groupItem($this->f)]);

        $this->actingAs($this->staff)->get(route('bookings.index'))->assertOk()->assertDontSee('deleteBooking');
        $this->actingAs($this->f['owner'])->get(route('bookings.index'))->assertSee('deleteBooking');
    });
});

describe('changing a booking (US-16)', function () {
    it('opens the booking in its own cart, re-checks the rules and saves the change', function () {
        $booking = ($this->book)([groupItem($this->f, ['days' => 5])]);
        $newCart = app(Cart::class);
        $newCart->add($this->f['school'], privateItem($this->f)); // an unrelated new booking in progress

        Livewire::test(Show::class, ['booking' => $booking])->call('editBooking', $booking->id)
            ->assertRedirect(route('booking', ['booking' => $booking->id]));

        $editCart = app(Cart::class)->forBooking($booking->id);
        $key = array_key_first($editCart->items($this->f['school']));
        expect($editCart->item($this->f['school'], $key)['days'])->toBe(5);

        Livewire::withQueryParams(['booking' => $booking->id])->test(Shop::class)
            ->assertSee($booking->number)
            ->call('editCartItem', $key)
            ->set('item.days', 3)
            ->call('addStudent', $this->f['tom']->id)
            ->call('addToCart');

        Livewire::withQueryParams(['booking' => $booking->id])->test(Checkout::class)
            ->assertSet('booker_name', 'Petra Brandner')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect(route('bookings.show', $booking));

        $booking->refresh()->load('lines.students', 'lines.lessonDays');
        expect($booking->lines)->toHaveCount(1)
            ->and($booking->lines[0]->units)->toBe(3)
            ->and($booking->lines[0]->lessonDays)->toHaveCount(3)
            ->and($booking->lines[0]->students)->toHaveCount(2)
            ->and($booking->total_cents)->toBe(2 * 15000)
            ->and($booking->number)->toBe('2026-0001')
            ->and($booking->events()->where('action', 'edited')->exists())->toBeTrue()
            // the other cart was left alone
            ->and($newCart->items($this->f['school']))->toHaveCount(1)
            ->and($editCart->isEmpty($this->f['school']))->toBeTrue();
    });

    it('refuses an invalid change', function () {
        $booking = ($this->book)([groupItem($this->f)]);
        $this->f['kids']->update(['is_active' => false]);

        $cart = app(Cart::class)->forBooking($booking->id);
        $cart->fill($this->f['school'], BookingItems::fromBooking($booking), []);

        Livewire::withQueryParams(['booking' => $booking->id])->test(Checkout::class)
            ->call('placeOrder')
            ->assertHasErrors('cart');

        expect($booking->fresh()->total_cents)->toBe(25000);
    });

    it('keeps the voucher and recalculates its discount without counting it twice', function () {
        $voucher = Voucher::create(['code' => 'P10', 'discount_type' => 'percent', 'discount_value' => 1000]);
        $booking = ($this->book)([groupItem($this->f, ['days' => 5])], 'Petra', 'P10');

        $cart = app(Cart::class)->forBooking($booking->id);
        $cart->fill($this->f['school'], [groupItem($this->f, ['days' => 2])], []);
        Livewire::withQueryParams(['booking' => $booking->id])->test(Checkout::class)->call('placeOrder');

        expect($booking->fresh())->discount_cents->toBe(1000)->total_cents->toBe(9000)
            ->and($voucher->fresh()->times_used)->toBe(1);
    });

    it('cannot change a cancelled booking', function () {
        $booking = ($this->book)([groupItem($this->f)]);
        $booking->forceFill(['cancelled_at' => now()])->save();

        $this->get(route('booking', ['booking' => $booking->id]))->assertNotFound();
    });
});

it('never shows or changes another school\'s bookings', function () {
    $booking = ($this->book)([groupItem($this->f)]);
    $other = bookingSchool(); // signs in as the other school's owner

    // A real browser request: the school is set by the middleware.
    app(CurrentSchool::class)->set(null);
    $this->actingAs($other['owner'])->get(route('bookings.show', $booking->id))->assertNotFound();

    $this->signIn($other['owner']);
    Livewire::test(Index::class)->assertDontSee('Petra Brandner');
    expect(fn () => Livewire::test(Index::class)->call('togglePaid', $booking->id))
        ->toThrow(ModelNotFoundException::class);
});
