<?php

use App\Domain\Booking\ChangeBookingStatus;
use App\Domain\Booking\PlaceOrder;
use App\Domain\Booking\UpdateBooking;
use App\Domain\Grouping\Grouping;
use App\Domain\Grouping\GroupManager;
use App\Enums\TimeslotKey;
use App\Livewire\Grouping\Index;
use App\Models\Booking;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Booking/BookingSetup.php';

beforeEach(function () {
    Notification::fake();
    $this->travelTo('2026-12-01 09:00:00');
    $this->f = bookingSchool();
    $this->monday = CarbonImmutable::parse('2027-01-11');
    $this->book = fn (array $items, string $name = 'Familie Test'): Booking => app(PlaceOrder::class)->handle(
        $this->f['school'], $this->f['owner'], $items, [], [
            'booker_name' => $name, 'email' => 'x@example.com', 'phone' => '', 'street' => 'Weg', 'house_number' => '1',
            'postal_code' => '6020', 'city' => 'Innsbruck', 'country' => 'AT', 'payment_method' => 'cash',
        ], null);
    $this->kid = fn (string $level = 'Blau', string $dob = '2017-03-03') => Student::factory()->create([
        'school_id' => $this->f['school']->id, 'date_of_birth' => $dob,
        'skill_level_id' => $this->f['school']->skillLevels()->where('name', $level)->value('id'),
    ]);
    $this->ski = function (array $override = []) {
        $instructor = Instructor::factory()->create(array_merge(['school_id' => $this->f['school']->id], $override));
        $instructor->sports()->sync([$this->f['ski']->id]);

        return $instructor;
    };
    $this->cohorts = fn (?CarbonImmutable $d = null) => app(Grouping::class)->cohorts($d ?? $this->monday);
});

describe('counting (US-22)', function () {
    it('counts group-course students by sport and age group, and works out the instructors needed', function () {
        // 10 kids, planned group size 8 (blank form default) → 2 instructors
        $kids = collect(range(1, 10))->map(fn () => ($this->kid)());
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => $kids->map(fn ($k) => ['id' => $k->id])->all()])]);

        $cohorts = ($this->cohorts)();
        expect($cohorts)->toHaveCount(1);
        $c = $cohorts->first();
        expect($c->sport->name)->toBe('Ski')
            ->and($c->ageGroup->name)->toBe('Kids')
            ->and($c->studentCount())->toBe(10)
            ->and($c->instructorsNeeded())->toBe(2)
            ->and($c->levelCounts()->all())->toBe(['Blau' => 10]);
    });

    it('counts a Mini booked into the Kids course with the Kids', function () {
        $mini = ($this->kid)('Blau', '2022-06-01');
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => [['id' => $mini->id, 'age_override' => true]]])]);

        expect(($this->cohorts)()->first()->ageGroup->name)->toBe('Kids');
    });

    it('lists private lessons by time, not in the counts', function () {
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '13:30'])]);
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '10:00'])]);

        expect(($this->cohorts)())->toHaveCount(0)
            ->and(app(Grouping::class)->privateLessons($this->monday)->map(fn ($l) => substr($l->starts_at, 0, 5))->all())->toBe(['10:00', '13:30']);
    });

    it('leaves cancelled bookings out and removes their students from groups (US-16)', function () {
        $booking = ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);
        app(GroupManager::class)->autoSplit(($this->cohorts)()->first());
        expect(GroupMember::count())->toBe(1);

        app(ChangeBookingStatus::class)->cancel($booking, $this->f['owner']);

        expect(GroupMember::count())->toBe(0)
            ->and(($this->cohorts)()->first()->studentCount())->toBe(0);
    });
});

describe('forming groups', function () {
    it('forms as many groups as instructors are needed, keeping similar levels together', function () {
        $students = collect([...array_fill(0, 5, 'Grün'), ...array_fill(0, 5, 'Rot')])->map(fn ($l) => ($this->kid)($l));
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => $students->map(fn ($s) => ['id' => $s->id])->all()])]);

        app(GroupManager::class)->autoSplit(($this->cohorts)()->first());

        $c = ($this->cohorts)()->first();
        expect($c->groups)->toHaveCount(2)
            ->and($c->unassigned())->toHaveCount(0)
            ->and($c->entriesOf($c->groups[0])->map(fn ($e) => $e->student->skillLevel->name)->unique()->values()->all())->toBe(['Grün']);
    });

    it('starts the next day like the previous day', function () {
        $a = ($this->kid)();
        $b = ($this->kid)();
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => [['id' => $a->id], ['id' => $b->id]]])]);
        $anna = ($this->ski)();

        $sunday = CarbonImmutable::parse('2027-01-10');
        app(GroupManager::class)->autoSplit(($this->cohorts)($sunday)->first());
        $group = Group::firstOrFail();
        app(GroupManager::class)->assignGroup($group, $anna->id, $this->f['school']);

        $n = app(GroupManager::class)->copyPreviousDay(($this->cohorts)()->first(), $this->f['school']);

        $monday = ($this->cohorts)()->first();
        expect($n)->toBe(2)
            ->and($monday->groups)->toHaveCount(1)
            ->and($monday->groups[0]->name)->toBe($group->name)
            ->and($monday->groups[0]->instructor_id)->toBe($anna->id)
            ->and($monday->unassigned())->toHaveCount(0);
    });

    it('moves students between groups, also into another age group of the same sport and time', function () {
        $a = ($this->kid)();
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => [['id' => $a->id]]])]);
        $c = ($this->cohorts)()->first();
        $g1 = app(GroupManager::class)->createGroup($this->monday, $c->timeslot, $c->sport->id, $c->ageGroup->id);
        $adults = $this->f['school']->ageGroups()->where('name', 'Erwachsene')->first();
        $g2 = app(GroupManager::class)->createGroup($this->monday, $c->timeslot, $c->sport->id, $adults->id);
        $entry = $c->entries->first();

        app(GroupManager::class)->moveStudent($entry->lessonDay->id, $a->id, $g1->id);
        app(GroupManager::class)->moveStudent($entry->lessonDay->id, $a->id, $g2->id);

        expect(GroupMember::where('student_id', $a->id)->value('group_id'))->toBe($g2->id)
            ->and(($this->cohorts)()->get("morning-{$c->sport->id}-{$adults->id}")->entries)->toHaveCount(1);

        app(GroupManager::class)->moveStudent($entry->lessonDay->id, $a->id, null);
        expect(GroupMember::count())->toBe(0);
    });

    it('does not move a student into a group at another time', function () {
        $a = ($this->kid)();
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10', 'students' => [['id' => $a->id]]])]);
        $c = ($this->cohorts)()->first();
        $afternoon = app(GroupManager::class)->createGroup($this->monday, TimeslotKey::Afternoon, $c->sport->id, $c->ageGroup->id);

        app(GroupManager::class)->moveStudent($c->entries->first()->lessonDay->id, $a->id, $afternoon->id);
    })->throws(ValidationException::class);
});

describe('assigning instructors (US-24, US-30)', function () {
    beforeEach(function () {
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);
        $c = ($this->cohorts)()->first();
        $this->group = app(GroupManager::class)->createGroup($this->monday, $c->timeslot, $c->sport->id, $c->ageGroup->id);
        $this->private = LessonDay::find(($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '11:00', 'hours' => 2])])->lines[0]->lessonDays[0]->id);
    });

    it('assigns and removes an instructor', function () {
        $anna = ($this->ski)();

        app(GroupManager::class)->assignGroup($this->group, $anna->id, $this->f['school']);
        expect($this->group->fresh()->instructor_id)->toBe($anna->id);

        app(GroupManager::class)->assignGroup($this->group, null, $this->f['school']);
        expect($this->group->fresh()->instructor_id)->toBeNull();
    });

    it('refuses inactive instructors', function () {
        $paul = ($this->ski)(['is_active' => false]);

        app(GroupManager::class)->assignGroup($this->group, $paul->id, $this->f['school']);
    })->throws(ValidationException::class);

    it('refuses overlapping assignments: morning group 10–12 and a private lesson 11–13', function () {
        $anna = ($this->ski)();
        app(GroupManager::class)->assignGroup($this->group, $anna->id, $this->f['school']);

        expect(fn () => app(GroupManager::class)->assignPrivate($this->private->fresh('line'), $anna->id, $this->f['school']))
            ->toThrow(ValidationException::class);
        expect($this->private->fresh()->instructor_id)->toBeNull();
    });

    it('allows back-to-back lessons that do not overlap', function () {
        $anna = ($this->ski)();
        app(GroupManager::class)->assignGroup($this->group, $anna->id, $this->f['school']); // 10:00–12:00
        $later = LessonDay::find(($this->book)([privateItem($this->f, ['start_date' => '2027-01-11', 'start_time' => '12:00', 'hours' => 1])])->lines[0]->lessonDays[0]->id);

        app(GroupManager::class)->assignPrivate($later->load('line'), $anna->id, $this->f['school']);

        expect($later->fresh()->instructor_id)->toBe($anna->id);
    });

    it('warns when the instructor does not teach the sport, and assigns after confirmation', function () {
        $lena = Instructor::factory()->create(['school_id' => $this->f['school']->id]); // teaches nothing

        $warnings = app(GroupManager::class)->assignGroup($this->group, $lena->id, $this->f['school']);
        expect($warnings)->toHaveCount(1)->and($this->group->fresh()->instructor_id)->toBeNull();

        app(GroupManager::class)->assignGroup($this->group, $lena->id, $this->f['school'], confirmed: true);
        expect($this->group->fresh()->instructor_id)->toBe($lena->id);
    });
});

it('keeps group places and private instructors when a booking is changed', function () {
    $a = ($this->kid)();
    $booking = ($this->book)([
        groupItem($this->f, ['start_date' => '2027-01-10', 'days' => 3, 'students' => [['id' => $a->id]]]),
        privateItem($this->f, ['start_date' => '2027-01-11']),
    ]);
    app(GroupManager::class)->autoSplit(($this->cohorts)()->first());
    $anna = ($this->ski)();
    app(GroupManager::class)->assignPrivate($booking->lines->firstWhere('course_type.value', 'private')->lessonDays[0]->load('line'), $anna->id, $this->f['school']);

    // Change: one more day, the private lesson unchanged.
    app(UpdateBooking::class)->handle($booking, $this->f['school'], $this->f['owner'], [
        groupItem($this->f, ['start_date' => '2027-01-10', 'days' => 4, 'students' => [['id' => $a->id]]]),
        privateItem($this->f, ['start_date' => '2027-01-11']),
    ], [], ['booker_name' => 'Familie Test', 'email' => 'x@example.com', 'street' => 'Weg', 'house_number' => '1', 'postal_code' => '6020', 'city' => 'Innsbruck', 'country' => 'AT', 'payment_method' => 'cash']);

    expect(GroupMember::where('student_id', $a->id)->whereHas('lessonDay', fn ($q) => $q->whereDate('date', '2027-01-11'))->exists())->toBeTrue()
        ->and(LessonDay::whereNotNull('starts_at')->value('instructor_id'))->toBe($anna->id);
});

describe('the screen (US-22, US-23)', function () {
    it('shows the day with counts, groups and private lessons', function () {
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);
        ($this->book)([privateItem($this->f, ['start_date' => '2027-01-11'])]);

        $this->get(route('grouping', ['date' => '2027-01-11']))->assertOk()
            ->assertSee('Kids')->assertSee('Lisa Brandner')->assertSee(__('Private lessons'));
    });

    it('moves through days and weeks', function () {
        Livewire::withQueryParams(['date' => '2027-01-11'])->test(Index::class)
            ->call('next')->assertSet('date', '2027-01-12')
            ->call('previous')->call('previous')->assertSet('date', '2027-01-10')
            ->set('view', 'week')->call('next')->assertSet('date', '2027-01-17');
    });

    it('shows a week starting on the school\'s first weekday', function () {
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);

        Livewire::withQueryParams(['date' => '2027-01-13', 'view' => 'week'])->test(Index::class)
            ->assertSee(__('Week overview'))
            ->assertViewHas('weekStart', fn ($d) => $d->toDateString() === '2027-01-10'); // Sunday
    });

    it('assigns from the screen and asks before a sport the instructor does not teach', function () {
        ($this->book)([groupItem($this->f, ['start_date' => '2027-01-10'])]);
        $c = ($this->cohorts)()->first();
        $group = app(GroupManager::class)->createGroup($this->monday, $c->timeslot, $c->sport->id, $c->ageGroup->id);
        $lena = Instructor::factory()->create(['school_id' => $this->f['school']->id]);

        Livewire::withQueryParams(['date' => '2027-01-11'])->test(Index::class)
            ->call('assignGroup', $group->id, (string) $lena->id)
            ->assertSet('pending.instructor', $lena->id)
            ->call('confirmPending');

        expect($group->fresh()->instructor_id)->toBe($lena->id);
    });

    it('is open to staff', function () {
        $staff = User::factory()->forSchool($this->f['school'])->create();

        $this->actingAs($staff)->get(route('grouping'))->assertOk();
    });
});
