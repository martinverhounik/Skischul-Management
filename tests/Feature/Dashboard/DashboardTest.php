<?php

use App\Domain\Booking\ChangeBookingStatus;
use App\Domain\Booking\PlaceOrder;
use App\Domain\Calendar\CalendarEntry;
use App\Domain\Calendar\CalendarFeed;
use App\Domain\Calendar\DayLayout;
use App\Domain\Calendar\SaveCalendarEvent;
use App\Domain\Dashboard\WeekNumbers;
use App\Domain\Grouping\Grouping;
use App\Domain\Grouping\GroupManager;
use App\Livewire\Dashboard;
use App\Models\Booking;
use App\Models\CalendarEvent;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Booking/BookingSetup.php';

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-12-01 09:00:00');
    $this->f = bookingSchool();
    $this->school = $this->f['school'];
    $this->monday = CarbonImmutable::parse('2027-01-11');
    $this->book = fn (array $items, string $name = 'Familie Test'): Booking => app(PlaceOrder::class)->handle(
        $this->school, $this->f['owner'], $items, [], [
            'booker_name' => $name, 'email' => 'x@example.com', 'phone' => '', 'street' => 'Weg', 'house_number' => '1',
            'postal_code' => '6020', 'city' => 'Innsbruck', 'country' => 'AT', 'payment_method' => 'cash',
        ], null);
    $this->ski = function (array $override = []) {
        $instructor = Instructor::factory()->create(array_merge(['school_id' => $this->school->id], $override));
        $instructor->sports()->sync([$this->f['ski']->id]);

        return $instructor;
    };
    // A group on Monday morning (10:00–12:00) with Lisa in it.
    $this->mondayGroup = function () {
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);
        $cohort = app(Grouping::class)->cohorts($this->monday)->first();
        app(GroupManager::class)->autoSplit($cohort);

        return app(Grouping::class)->cohorts($this->monday)->first()->groups->first();
    };
    $this->feed = fn (string $from = '2027-01-10', string $to = '2027-01-16', ?int $course = null, ?int $instructor = null) => CalendarFeed::for($this->school)
        ->between(CarbonImmutable::parse($from), CarbonImmutable::parse($to), $course, $instructor);
    $this->event = fn (array $input, ?CalendarEvent $event = null) => app(SaveCalendarEvent::class)->save($this->school, $input, $event, $this->f['owner']);
});

describe('this week\'s numbers (US-25)', function () {
    beforeEach(function () {
        $this->a = ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])], 'A');              // Sun–Thu
        $this->b = ($this->book)([privateItem($this->f, ['start_date' => '2027-01-15'])], 'B');            // Friday
        $this->c = ($this->book)([privateItem($this->f, ['start_date' => '2027-01-18'])], 'C');            // next week
        $this->d = ($this->book)([privateItem($this->f, ['start_date' => '2027-01-12', 'start_time' => '14:00'])], 'D');
        app(ChangeBookingStatus::class)->setPaid($this->a, true, $this->f['owner']);
        app(ChangeBookingStatus::class)->cancel($this->d, $this->f['owner']);
    });

    it('counts bookings with a lesson this week (Sunday to Saturday) and today, without cancelled ones', function () {
        $n = new WeekNumbers($this->school, CarbonImmutable::parse('2027-01-12'));

        expect($n->weekStart->toDateString())->toBe('2027-01-10')
            ->and($n->activeThisWeek())->toBe(2)
            ->and($n->activeToday())->toBe(1)
            ->and($n->paidThisWeek())->toBe(1)
            ->and($n->unpaidThisWeek())->toBe(1)
            ->and($n->unpaidCentsThisWeek())->toBe($this->b->total_cents)
            ->and($n->unpaidList()->pluck('booker_name')->all())->toBe(['B']);
    });

    it('follows the school\'s first weekday', function () {
        $this->school->update(['week_starts_on' => 1]); // Monday
        $n = new WeekNumbers($this->school->fresh(), CarbonImmutable::parse('2027-01-17')); // a Sunday

        expect($n->weekStart->toDateString())->toBe('2027-01-11')
            ->and($n->activeThisWeek())->toBe(2); // A (Mon–Thu) and B (Fri)
    });

    it('shows the numbers and a quick link to the booking screen', function () {
        $this->travelTo('2027-01-12 09:00:00');

        $this->get(route('dashboard'))->assertOk()
            ->assertSee(__('Active bookings this week'))
            ->assertSee('href="'.route('booking').'"', false)
            ->assertSee(route('bookings.show', $this->b));
    });
});

describe('the calendar feed (US-26)', function () {
    it('shows groups with their instructor, private lessons and students still without a group', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)(['first_name' => 'Anna', 'last_name' => 'Berger']);
        app(GroupManager::class)->assignGroup($group, $anna->id, $this->school);
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '14:00'])]);

        $days = ($this->feed)();
        $monday = $days->get('2027-01-11');

        expect($days)->toHaveCount(7)
            ->and($monday->pluck('kind')->all())->toBe([CalendarEntry::GROUP, CalendarEntry::PRIVATE])
            ->and($monday[0]->instructorName)->toBe('Anna Berger')
            ->and($monday[0]->from.'–'.$monday[0]->to)->toBe('10:00–12:00')
            ->and($monday[1]->needsInstructor())->toBeTrue()
            // Sunday was not grouped yet
            ->and($days->get('2027-01-10')->first()->kind)->toBe(CalendarEntry::UNGROUPED)
            ->and($days->get('2027-01-10')->first()->detail)->toContain('1');
    });

    it('leaves cancelled bookings out', function () {
        $booking = ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11'])]);
        app(ChangeBookingStatus::class)->cancel($booking, $this->f['owner']);

        expect(($this->feed)()->flatten(1))->toHaveCount(0);
    });

    it('shows a standalone entry on every day it lasts', function () {
        ($this->event)(['title' => 'Urlaub', 'starts_on' => '2027-01-14', 'ends_on' => '2027-01-17', 'all_day' => true]);

        $days = ($this->feed)();
        expect($days->filter->isNotEmpty()->keys()->all())->toBe(['2027-01-14', '2027-01-15', '2027-01-16'])
            ->and($days->get('2027-01-14')->first()->allDay)->toBeTrue();
    });

    it('filters by course and by instructor', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)();
        app(GroupManager::class)->assignGroup($group, $anna->id, $this->school);
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '14:00'])]);
        ($this->event)(['title' => 'Besprechung', 'starts_on' => '2027-01-11', 'all_day' => false, 'starts_at' => '17:00', 'ends_at' => '18:00']);
        ($this->event)(['title' => 'Frei', 'starts_on' => '2027-01-12', 'all_day' => true, 'instructor_id' => $anna->id]);

        $byCourse = ($this->feed)(course: $this->f['private']->id)->flatten(1);
        $byInstructor = ($this->feed)(instructor: $anna->id)->flatten(1);

        expect($byCourse->pluck('kind')->unique()->values()->all())->toBe([CalendarEntry::PRIVATE])
            ->and(($this->feed)(course: $this->f['kids']->id)->get('2027-01-11')->pluck('kind')->all())->toBe([CalendarEntry::GROUP])
            ->and($byInstructor->map(fn ($e) => $e->kind.' '.$e->date->toDateString())->all())
            ->toBe([CalendarEntry::GROUP.' 2027-01-11', CalendarEntry::EVENT.' 2027-01-12']);
    });

    it('places overlapping entries side by side', function () {
        $e = fn ($from, $to) => new CalendarEntry('event', 1, $this->monday, $from, $to, false, 'x', '');
        $placed = DayLayout::lanes(collect([$e('10:00', '12:00'), $e('11:00', '13:00'), $e('12:00', '13:00'), $e('14:00', '15:00')]));

        expect(collect($placed)->map(fn ($p) => $p['entry']->from.':'.$p['lane'].'/'.$p['lanes'])->all())
            ->toBe(['10:00:0/2', '11:00:1/2', '12:00:0/2', '14:00:0/1']);
    });
});

describe('standalone entries and the overlap rule (US-26, US-30)', function () {
    it('creates, edits and deletes an entry', function () {
        $c = Livewire::test(Dashboard::class)
            ->call('newEvent', '2027-01-11')
            ->set('event.title', 'Teambesprechung')
            ->set('event.all_day', false)
            ->set('event.starts_at', '16:30')
            ->set('event.ends_at', '17:30')
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertSet('eventId', null);

        $event = CalendarEvent::sole();
        expect($event->starts_at)->toStartWith('16:30')->and($event->created_by)->toBe($this->f['owner']->id);

        $c->call('open', 'event', $event->id, '2027-01-11')
            ->assertSet('event.title', 'Teambesprechung')
            ->set('event.title', 'Teamsitzung')
            ->call('saveEvent');
        expect($event->fresh()->title)->toBe('Teamsitzung');

        $c->call('open', 'event', $event->id, '2027-01-11')->call('deleteEvent');
        expect(CalendarEvent::count())->toBe(0);
    });

    it('checks the entry form', function () {
        Livewire::test(Dashboard::class)
            ->call('newEvent', '2027-01-11')
            ->set('event.title', '')
            ->set('event.all_day', false)
            ->set('event.starts_at', '12:00')
            ->set('event.ends_at', '11:00')
            ->call('saveEvent')
            ->assertHasErrors(['event.title', 'event.ends_at']);

        expect(fn () => ($this->event)(['title' => 'x', 'starts_on' => '2027-01-11', 'ends_on' => '2027-01-10', 'all_day' => true]))
            ->toThrow(ValidationException::class)
            ->and(fn () => ($this->event)(['title' => 'x', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-01', 'all_day' => true]))
            ->toThrow(ValidationException::class);
    });

    it('refuses an entry for an instructor who teaches at that time, back-to-back is fine', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)();
        app(GroupManager::class)->assignGroup($group, $anna->id, $this->school);

        expect(fn () => ($this->event)(['title' => 'Arzt', 'starts_on' => '2027-01-11', 'all_day' => false, 'starts_at' => '09:00', 'ends_at' => '11:00', 'instructor_id' => $anna->id]))
            ->toThrow(ValidationException::class)
            ->and(fn () => ($this->event)(['title' => 'Urlaub', 'starts_on' => '2027-01-10', 'ends_on' => '2027-01-12', 'all_day' => true, 'instructor_id' => $anna->id]))
            ->toThrow(ValidationException::class);

        ($this->event)(['title' => 'Arzt', 'starts_on' => '2027-01-11', 'all_day' => false, 'starts_at' => '08:00', 'ends_at' => '10:00', 'instructor_id' => $anna->id]);
        expect(CalendarEvent::count())->toBe(1);
    });

    it('refuses an instructor for a course during their entry', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)();
        $lukas = ($this->ski)();
        ($this->event)(['title' => 'Urlaub', 'starts_on' => '2027-01-10', 'ends_on' => '2027-01-12', 'all_day' => true, 'instructor_id' => $anna->id]);
        ($this->event)(['title' => 'Arzt', 'starts_on' => '2027-01-11', 'all_day' => false, 'starts_at' => '11:30', 'ends_at' => '12:30', 'instructor_id' => $lukas->id]);

        expect(fn () => app(GroupManager::class)->assignGroup($group, $anna->id, $this->school))->toThrow(ValidationException::class, __(':name is not available on this day (:what).', ['name' => $anna->fullName(), 'what' => 'Urlaub']))
            ->and(fn () => app(GroupManager::class)->assignGroup($group, $lukas->id, $this->school))->toThrow(ValidationException::class, '11:30–12:30');

        // An entry for nobody in particular blocks no one.
        ($this->event)(['title' => 'Besprechung', 'starts_on' => '2027-01-11', 'all_day' => true]);
        $maria = ($this->ski)();
        app(GroupManager::class)->assignGroup($group, $maria->id, $this->school);
        expect($group->fresh()->instructor_id)->toBe($maria->id);
    });
});

describe('the calendar screen (US-26)', function () {
    it('assigns and removes an instructor, the same assignment as in Grouping', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)();

        $c = Livewire::withQueryParams(['date' => '2027-01-11'])->test(Dashboard::class)
            ->call('open', CalendarEntry::GROUP, $group->id, '2027-01-11')
            ->assertViewHas('selectedEntry', fn ($e) => $e->id === $group->id)
            ->call('assign', (string) $anna->id)
            ->assertHasNoErrors();
        expect($group->fresh()->instructor_id)->toBe($anna->id);

        $c->call('assign', '');
        expect($group->fresh()->instructor_id)->toBeNull();
    });

    it('applies the Grouping rules: refuses overlaps, asks about another sport', function () {
        $group = ($this->mondayGroup)();
        $anna = ($this->ski)();
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '11:00'])]);
        $private = LessonDay::query()->whereNotNull('starts_at')->get()->first(fn ($d) => substr($d->starts_at, 0, 5) === '11:00');
        $private->update(['instructor_id' => $anna->id]);
        $lena = Instructor::factory()->create(['school_id' => $this->school->id]); // teaches no sport

        Livewire::withQueryParams(['date' => '2027-01-11'])->test(Dashboard::class)
            ->call('open', CalendarEntry::GROUP, $group->id, '2027-01-11')
            ->call('assign', (string) $anna->id)
            ->assertHasErrors('instructor')
            ->call('assign', (string) $lena->id)
            ->assertSet('pending.instructor', $lena->id)
            ->call('confirmPending');

        expect($group->fresh()->instructor_id)->toBe($lena->id);
    });

    it('moves by day, week and month', function () {
        Livewire::withQueryParams(['date' => '2027-01-13'])->test(Dashboard::class)
            ->assertViewHas('from', fn ($d) => $d->toDateString() === '2027-01-10')
            ->call('next')->assertSet('date', '2027-01-20')
            ->set('view', 'month')->call('next')->assertSet('date', '2027-02-01')
            ->assertViewHas('from', fn ($d) => $d->toDateString() === '2027-01-31') // Sunday before 1 Feb
            ->assertViewHas('to', fn ($d) => $d->toDateString() === '2027-03-06')
            ->call('showDay', '2027-02-03')->assertSet('view', 'day')
            ->call('previous')->assertSet('date', '2027-02-02');
    });

    it('cannot open another school\'s entry', function () {
        $other = School::factory()->create();
        $foreign = app(CurrentSchool::class)->runAs($other, fn () => CalendarEvent::query()->create([
            'title' => 'Geheim', 'starts_on' => '2027-01-11', 'ends_on' => '2027-01-11',
        ]));

        expect(($this->feed)()->flatten(1))->toHaveCount(0)
            ->and(fn () => Livewire::test(Dashboard::class)->call('open', 'event', $foreign->id, '2027-01-11'))
            ->toThrow(ModelNotFoundException::class);
    });

    it('is open to staff', function () {
        $staff = User::factory()->forSchool($this->school)->create();

        $this->actingAs($staff)->get(route('dashboard', ['view' => 'month']))->assertOk()->assertSee(__('Month'));
    });
});
