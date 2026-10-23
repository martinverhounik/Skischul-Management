<?php

use App\Domain\Booking\Cart;
use App\Livewire\Booking\Checkout;
use App\Livewire\Booking\Shop;
use App\Models\AddOn;
use App\Models\Booking;
use App\Models\LessonDay;
use App\Models\Student;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\BookingConfirmation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/BookingSetup.php';

beforeEach(function () {
    $this->f = bookingSchool();
    $this->travel(0)->days();
    // Book "today" = 1 Dec 2026, before the season.
    $this->travelTo('2026-12-01 09:00:00');
});

function booker(): array
{
    return [
        'booker_name' => 'Petra Brandner', 'email' => 'Petra@Example.com', 'phone' => '+43 664 1111111',
        'street' => 'Dorfstraße', 'house_number' => '12', 'postal_code' => '6020', 'city' => 'Innsbruck',
        'country' => 'AT', 'payment_method' => 'card',
    ];
}

function checkout(array $override = []): mixed
{
    $component = Livewire::test(Checkout::class);
    foreach (array_merge(booker(), $override) as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

describe('the course list (US-09)', function () {
    $names = fn ($component) => $component->instance()->courses->map->displayName()->all();

    it('shows only courses available in the travel period and matching the filters', function () use ($names) {
        $c = Livewire::test(Shop::class)->assertSee('Kinderskikurs');
        expect($names($c))->toBe(['Kinderskikurs', 'Privatstunde']);

        expect($names($c->set('type', 'private')))->toBe(['Privatstunde']);
        expect($names($c->set('type', '')->set('level', (string) $this->f['black']->id)))->toBe(['Privatstunde']);
    });

    it('hides courses outside the season and in periods without a start day', function () use ($names) {
        // A Tuesday–Thursday trip: the kids course only starts Sun / Mon.
        expect($names(Livewire::test(Shop::class)->set('from', '2027-01-12')->set('to', '2027-01-14')))->toBe(['Privatstunde']);

        // Before the season: nothing.
        expect($names(Livewire::test(Shop::class)->set('from', '2026-11-20')->set('to', '2026-11-30')))->toBe([]);
    });

    it('offers only allowed start days as dates', function () {
        $c = Livewire::test(Shop::class)->set('from', '2027-01-04')->set('to', '2027-01-17')->call('configure', $this->f['kids']->id);

        // Sundays and Mondays only: 10, 11, 17 Jan (and none on other weekdays)
        $c->assertSeeHtml('value="2027-01-10"')->assertSeeHtml('value="2027-01-11"')->assertSeeHtml('value="2027-01-17"')
            ->assertDontSeeHtml('value="2027-01-12"')->assertDontSeeHtml('value="2027-01-16"');
    });
});

describe('configuring and the cart (US-10, US-11, US-12)', function () {
    it('configures a group course, adds a participant and puts it in the cart', function () {
        Livewire::test(Shop::class)
            ->set('from', '2027-01-10')->set('to', '2027-01-16')
            ->call('configure', $this->f['kids']->id)
            ->set('item.start_date', '2027-01-10')
            ->set('item.days', 5)
            ->set('item.timeslot_key', 'morning')
            ->set('studentSearch', 'Lisa')
            ->assertSee('Lisa Brandner')
            ->call('addStudent', $this->f['lisa']->id)
            ->assertSee('250,00')
            ->call('addToCart')
            ->assertSet('configuring', null);

        $items = app(Cart::class)->items($this->f['school']);
        expect($items)->toHaveCount(1)->and(array_values($items)[0]['days'])->toBe(5);
    });

    it('creates a new student from the booking screen and adds them', function () {
        Livewire::test(Shop::class)
            ->call('configure', $this->f['kids']->id)
            ->set('showNewStudent', true)
            ->set('newStudent.first_name', 'Neo')
            ->set('newStudent.last_name', 'Neu')
            ->set('newStudent.date_of_birth', '2017-04-04')
            ->set('newStudent.skill_level_id', (string) $this->f['blue']->id)
            ->set('newStudent.primary_phone', '+43 660 1234567')
            ->set('newStudent.primary_phone_owner', 'Vater')
            ->call('createStudent')
            ->assertHasNoErrors()
            ->assertSee('Neo Neu');

        expect(Student::where('first_name', 'Neo')->exists())->toBeTrue();
    });

    it('does not add an invalid course to the cart', function () {
        $pro = Student::factory()->create(['school_id' => $this->f['school']->id, 'skill_level_id' => $this->f['black']->id]);

        Livewire::test(Shop::class)
            ->call('configure', $this->f['kids']->id)
            ->call('addStudent', $pro->id)
            ->call('addToCart');

        expect(app(Cart::class)->items($this->f['school']))->toBe([]);
    });

    it('asks before booking a student outside the age groups', function () {
        $mini = Student::factory()->create(['school_id' => $this->f['school']->id, 'skill_level_id' => $this->f['blue']->id, 'date_of_birth' => '2022-06-01']);

        $c = Livewire::test(Shop::class)->call('configure', $this->f['kids']->id)->call('addStudent', $mini->id)
            ->assertSee(__('Book anyway'))
            ->call('addToCart');
        expect(app(Cart::class)->items($this->f['school']))->toBe([]);

        $c->call('allowAge', $mini->id)->call('addToCart');
        expect(app(Cart::class)->items($this->f['school']))->toHaveCount(1);
    });

    it('edits and removes cart items and sets add-on quantities', function () {
        $cart = app(Cart::class);
        $key = $cart->add($this->f['school'], groupItem($this->f));
        $lunch = AddOn::create(['name' => 'Mittagsbetreuung', 'price_cents' => 2200]);

        Livewire::test(Shop::class)
            ->call('editCartItem', $key)
            ->set('item.days', 3)
            ->call('addToCart')
            ->call('setAddOn', $lunch->id, 2);

        expect($cart->item($this->f['school'], $key)['days'])->toBe(3)
            ->and($cart->addOns($this->f['school']))->toBe([$lunch->id => 2])
            ->and($cart->subtotal($this->f['school']))->toBe(15000 + 4400);

        Livewire::test(Shop::class)->call('removeFromCart', $key);
        expect($cart->items($this->f['school']))->toBe([]);
    });

    it('books a second private lesson with the same participants', function () {
        $cart = app(Cart::class);
        $key = $cart->add($this->f['school'], privateItem($this->f));

        Livewire::test(Shop::class)->call('addAnotherLesson', $key)
            ->assertSet('item.start_date', '2027-01-13')
            ->call('addToCart');

        expect($cart->items($this->f['school']))->toHaveCount(2);
    });
});

describe('checkout (US-13, US-28, US-29)', function () {
    beforeEach(function () {
        $this->cart = app(Cart::class);
        $this->cart->add($this->f['school'], groupItem($this->f, ['students' => [['id' => $this->f['lisa']->id], ['id' => $this->f['tom']->id]]]));
        $this->cart->add($this->f['school'], privateItem($this->f));
    });

    it('places the booking with everything in it and emails the booker', function () {
        Notification::fake();
        $lunch = AddOn::create(['name' => 'Mittagsbetreuung', 'price_cents' => 2200]);
        $this->cart->setAddOn($this->f['school'], $lunch->id, 5);

        checkout()->call('placeOrder')->assertHasNoErrors();

        $booking = Booking::with('lines.students', 'lines.lessonDays', 'addOns')->firstOrFail();
        expect($booking)
            ->number->toBe('2026-0001')
            ->email->toBe('petra@example.com')
            ->subtotal_cents->toBe(2 * 25000 + 14000 + 5 * 2200)
            ->total_cents->toBe($booking->subtotal_cents)
            ->payment_status->value->toBe('unpaid')
            ->created_by->toBe($this->f['owner']->id)
            ->and($booking->lines)->toHaveCount(2)
            ->and($booking->lines[0]->students)->toHaveCount(2)
            ->and($booking->lines[0]->lessonDays)->toHaveCount(5)
            ->and($booking->lines[1]->start_time)->toStartWith('10:00')
            ->and($booking->addOns[0]->quantity)->toBe(5)
            ->and($booking->confirmation_sent_at)->not->toBeNull()
            ->and(LessonDay::count())->toBe(6)
            ->and($this->cart->isEmpty($this->f['school']))->toBeTrue();

        Notification::assertSentOnDemand(BookingConfirmation::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === ['petra@example.com' => 'Petra Brandner']);
    });

    it('requires the booker details', function () {
        Livewire::test(Checkout::class)->call('placeOrder')
            ->assertHasErrors(['booker_name', 'email', 'street', 'house_number', 'postal_code', 'city']);
        expect(Booking::count())->toBe(0);
    });

    it('applies a valid voucher and counts its use', function () {
        $voucher = Voucher::create(['code' => 'WINTER10', 'discount_type' => 'percent', 'discount_value' => 1000, 'max_uses' => 1]);

        checkout(['voucherInput' => 'winter10'])->call('applyVoucher')->assertSet('voucherCode', 'WINTER10')->call('placeOrder');

        $booking = Booking::firstOrFail();
        expect($booking->discount_cents)->toBe(6400)
            ->and($booking->total_cents)->toBe(64000 - 6400)
            ->and($voucher->fresh()->times_used)->toBe(1);
    });

    it('rejects expired, inactive and used-up vouchers with a clear message', function () {
        Voucher::create(['code' => 'OLD', 'discount_type' => 'fixed', 'discount_value' => 500, 'valid_until' => '2026-11-30']);
        Voucher::create(['code' => 'OFF', 'discount_type' => 'fixed', 'discount_value' => 500, 'is_active' => false]);

        checkout(['voucherInput' => 'OLD'])->call('applyVoucher')->assertHasErrors('voucherInput')->assertSee(__('This voucher has expired.'));
        checkout(['voucherInput' => 'OFF'])->call('applyVoucher')->assertHasErrors('voucherInput');
        checkout(['voucherInput' => 'NOPE'])->call('applyVoucher')->assertSee(__('This voucher code does not exist.'));
    });

    it('checks everything again when placing the order', function () {
        // The course gets switched off after it went into the cart.
        $this->f['kids']->update(['is_active' => false]);

        checkout()->call('placeOrder')->assertHasErrors('cart');
        expect(Booking::count())->toBe(0);
    });

    it('keeps the booking if the email cannot be sent (US-29)', function () {
        Notification::shouldReceive('route')->andThrow(new RuntimeException('SMTP down'));

        checkout()->call('placeOrder')->assertHasNoErrors();

        $booking = Booking::firstOrFail();
        expect($booking->confirmation_sent_at)->toBeNull()
            ->and($booking->events()->where('action', 'email_failed')->exists())->toBeTrue();
    });

    it('numbers bookings per school and year', function () {
        Notification::fake();
        checkout()->call('placeOrder');
        app(Cart::class)->add($this->f['school'], privateItem($this->f, ['start_date' => '2027-01-15']));
        checkout()->call('placeOrder');

        expect(Booking::orderBy('id')->pluck('number')->all())->toBe(['2026-0001', '2026-0002']);
    });

    it('does not let a booked price table, voucher or add-on be deleted (US-30)', function () {
        Notification::fake();
        $lunch = AddOn::create(['name' => 'Mittag', 'price_cents' => 2200]);
        $this->cart->setAddOn($this->f['school'], $lunch->id, 1);
        $voucher = Voucher::create(['code' => 'X5', 'discount_type' => 'fixed', 'discount_value' => 500]);
        checkout(['voucherInput' => 'X5'])->call('applyVoucher')->call('placeOrder');

        foreach ([$this->f['kids'], $lunch, $voucher] as $record) {
            expect(fn () => $record->deleteIfUnused())->toThrow(ValidationException::class);
        }
    });

    it('shows the booking afterwards', function () {
        Notification::fake();
        checkout()->call('placeOrder');
        $booking = Booking::firstOrFail();

        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('2026-0001')->assertSee('Lisa Brandner')->assertSee('Petra Brandner');
    });
});

it('is open to staff as well (US-27)', function () {
    $staff = User::factory()->forSchool($this->f['school'])->create();

    $this->actingAs($staff)->get(route('booking'))->assertOk();
});
