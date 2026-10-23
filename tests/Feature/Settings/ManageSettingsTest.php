<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Enums\TimeslotKey;
use App\Livewire\Settings;
use App\Models\AddOn;
use App\Models\AgeGroup;
use App\Models\ClosedDay;
use App\Models\MeetingPoint;
use App\Models\School;
use App\Models\SkillLevel;
use App\Models\Sport;
use App\Models\Timeslot;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

/** A record that belongs to another school (school_id is not mass assignable). */
function foreignRecord(string $model, School $school, array $attributes): Model
{
    $record = new $model;
    $record->forceFill($attributes + ['school_id' => $school->id])->save();

    return $record;
}

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->owner = User::factory()->forSchool($this->school)->owner()->create();
    $this->signIn($this->owner);
});

describe('defaults for a new school', function () {
    it('starts with timeslots, sports, skill levels and age groups', function () {
        expect(Timeslot::count())->toBe(5)
            ->and(Sport::pluck('name')->all())->toBe(['Ski', 'Snowboard'])
            ->and(SkillLevel::orderBy('position')->pluck('name')->all())->toBe(['Grün', 'Blau', 'Rot', 'Schwarz'])
            ->and(AgeGroup::count())->toBe(3);
    });

    it('can run twice without duplicating anything', function () {
        app(CreateSchoolDefaults::class)->handle($this->school);

        expect(Timeslot::count())->toBe(5)->and(Sport::count())->toBe(2);
    });
});

describe('general (US-01, plan questions 7, 9, 12)', function () {
    it('saves name, language, week start, the full-day rule and the lunch add-on', function () {
        $lunch = AddOn::create(['name' => 'Mittag', 'price_cents' => 2000]);

        Livewire::test(Settings\General::class)
            ->set('name', 'Skischule Neu')
            ->set('week_starts_on', 1)
            ->set('enforce_full_day_price', false)
            ->set('lunch_add_on_id', $lunch->id)
            ->call('save')
            ->assertHasNoErrors();

        expect($this->school->fresh())
            ->name->toBe('Skischule Neu')
            ->week_starts_on->toBe(1)
            ->enforce_full_day_price->toBeFalse()
            ->lunch_add_on_id->toBe($lunch->id);
    });

    it('does not accept an add-on of another school for lunch', function () {
        $other = School::factory()->create();
        $foreign = foreignRecord(AddOn::class, $other, ['name' => 'X', 'price_cents' => 1]);

        Livewire::test(Settings\General::class)
            ->set('lunch_add_on_id', $foreign->id)
            ->call('save')
            ->assertHasErrors('lunch_add_on_id');
    });
});

describe('season and closed days (US-01)', function () {
    it('saves the season', function () {
        Livewire::test(Settings\Season::class)
            ->set('season_starts_on', '2026-12-05')
            ->set('season_ends_on', '2027-04-11')
            ->call('saveSeason')
            ->assertHasNoErrors();

        expect($this->school->fresh()->season_ends_on->toDateString())->toBe('2027-04-11');
    });

    it('refuses an end before the start', function () {
        Livewire::test(Settings\Season::class)
            ->set('season_starts_on', '2027-04-11')
            ->set('season_ends_on', '2026-12-05')
            ->call('saveSeason')
            ->assertHasErrors('season_ends_on');
    });

    it('adds a single closed day and a range of closed days', function () {
        Livewire::test(Settings\Season::class)
            ->set('closed_from', '2026-12-24')
            ->set('closed_note', 'Heiligabend')
            ->call('addClosedDays')
            ->set('closed_from', '2027-01-10')
            ->set('closed_until', '2027-01-12')
            ->call('addClosedDays')
            ->assertHasNoErrors();

        expect(ClosedDay::orderBy('date')->get()->map(fn ($d) => $d->date->toDateString())->all())
            ->toBe(['2026-12-24', '2027-01-10', '2027-01-11', '2027-01-12']);
    });

    it('does not create the same closed day twice', function () {
        Livewire::test(Settings\Season::class)
            ->set('closed_from', '2026-12-24')->call('addClosedDays')
            ->set('closed_from', '2026-12-24')->call('addClosedDays');

        expect(ClosedDay::count())->toBe(1);
    });

    it('removes a closed day', function () {
        $day = ClosedDay::create(['date' => '2026-12-24']);

        Livewire::test(Settings\Season::class)->call('removeClosedDay', $day->id);

        expect(ClosedDay::count())->toBe(0);
    });
});

describe('course times (US-06)', function () {
    it('edits the timeslots', function () {
        Livewire::test(Settings\Times::class)
            ->set('slots.morning.starts_at', '09:30')
            ->set('slots.morning.ends_at', '12:00')
            ->call('save')
            ->assertHasNoErrors();

        expect(Timeslot::where('key', TimeslotKey::Morning)->first()->startsAtLabel())->toBe('09:30');
    });

    it('requires the end to be after the start', function () {
        Livewire::test(Settings\Times::class)
            ->set('slots.afternoon.starts_at', '15:00')
            ->set('slots.afternoon.ends_at', '14:00')
            ->call('save')
            ->assertHasErrors('slots.afternoon.ends_at');
    });
});

describe('sports and skill levels (US-02, US-03)', function () {
    it('adds, renames and deletes a sport', function () {
        $component = Livewire::test(Settings\Sports::class)
            ->set('new.name', 'Langlauf')
            ->call('create')
            ->assertHasNoErrors();

        $sport = Sport::where('name', 'Langlauf')->firstOrFail();

        $component->call('startEditing', $sport->id)
            ->set('edit.name', 'Langlaufen')
            ->call('update')
            ->assertHasNoErrors();
        expect($sport->fresh()->name)->toBe('Langlaufen');

        $component->call('delete', $sport->id);
        expect(Sport::find($sport->id))->toBeNull();
    });

    it('does not allow the same name twice in a school, but in different schools', function () {
        Livewire::test(Settings\Sports::class)
            ->set('new.name', 'Ski')
            ->call('create')
            ->assertHasErrors('new.name');

        $other = School::factory()->create();
        foreignRecord(Sport::class, $other, ['name' => 'Langlauf']);

        Livewire::test(Settings\Sports::class)->set('new.name', 'Langlauf')->call('create')->assertHasNoErrors();
    });

    it('reorders skill levels from beginner to advanced', function () {
        $blue = SkillLevel::where('name', 'Blau')->first();

        Livewire::test(Settings\SkillLevels::class)->call('move', $blue->id, 'up');

        expect(SkillLevel::orderBy('position')->pluck('name')->all())->toBe(['Blau', 'Grün', 'Rot', 'Schwarz']);
    });

    it('adds new skill levels at the end', function () {
        Livewire::test(Settings\SkillLevels::class)->set('new.name', 'Freeride')->call('create');

        expect(SkillLevel::orderBy('position')->pluck('name')->last())->toBe('Freeride');
    });
});

describe('age groups (US-04)', function () {
    it('requires max age to be at least min age', function () {
        Livewire::test(Settings\AgeGroups::class)
            ->set('new.name', 'Teens')
            ->set('new.min_age', 15)
            ->set('new.max_age', 12)
            ->call('create')
            ->assertHasErrors('new.max_age');
    });

    it('allows overlapping age groups', function () {
        Livewire::test(Settings\AgeGroups::class)
            ->set('new.name', 'Teens')
            ->set('new.min_age', 12)
            ->set('new.max_age', 17)
            ->call('create')
            ->assertHasNoErrors();

        expect(AgeGroup::where('name', 'Teens')->first()->includesAge(13))->toBeTrue();
    });
});

describe('meeting points (US-05)', function () {
    it('adds a meeting point with an optional map link', function () {
        Livewire::test(Settings\MeetingPoints::class)
            ->set('new.name', 'Talstation')
            ->set('new.map_url', 'https://maps.example.com/talstation')
            ->call('create')
            ->set('new.name', 'Kinderland')
            ->call('create')
            ->assertHasNoErrors();

        expect(MeetingPoint::where('name', 'Kinderland')->first()->map_url)->toBeNull();
    });

    it('only accepts web links as map links', function () {
        Livewire::test(Settings\MeetingPoints::class)
            ->set('new.name', 'Talstation')
            ->set('new.map_url', 'javascript:alert(1)')
            ->call('create')
            ->assertHasErrors('new.map_url');
    });
});

describe('add-ons (US-08)', function () {
    it('stores prices typed with a comma as cents', function () {
        Livewire::test(Settings\AddOns::class)
            ->set('new.name', 'Mittagsbetreuung')
            ->set('new.price', '22,50')
            ->call('create')
            ->assertHasNoErrors();

        expect(AddOn::first()->price_cents)->toBe(2250);
    });

    it('rejects a price that is not a number', function () {
        Livewire::test(Settings\AddOns::class)
            ->set('new.name', 'Mittag')
            ->set('new.price', 'gratis')
            ->call('create')
            ->assertHasErrors('new.price');
    });

    it('can be switched off', function () {
        $addOn = AddOn::create(['name' => 'Verleih', 'price_cents' => 1900]);

        Livewire::test(Settings\AddOns::class)->call('toggleActive', $addOn->id);

        expect($addOn->fresh()->is_active)->toBeFalse();
    });
});

describe('vouchers (US-28)', function () {
    it('creates a percentage voucher with a validity period and usage limit', function () {
        Livewire::test(Settings\Vouchers::class)
            ->set('new.code', 'winter10')
            ->set('new.discount_type', 'percent')
            ->set('new.amount', '10')
            ->set('new.valid_until', '2027-01-31')
            ->set('new.max_uses', 50)
            ->call('create')
            ->assertHasNoErrors();

        $voucher = Voucher::first();
        expect($voucher)
            ->code->toBe('WINTER10')
            ->discount_value->toBe(1000)
            ->max_uses->toBe(50);
    });

    it('does not allow more than 100 percent', function () {
        Livewire::test(Settings\Vouchers::class)
            ->set('new.code', 'ALLES')
            ->set('new.discount_type', 'percent')
            ->set('new.amount', '120')
            ->call('create')
            ->assertHasErrors('new.amount');
    });

    it('calculates the discount and never goes below zero', function () {
        $fixed = Voucher::create(['code' => 'MINUS20', 'discount_type' => 'fixed', 'discount_value' => 2000]);
        $percent = Voucher::create(['code' => 'P10', 'discount_type' => 'percent', 'discount_value' => 1000]);

        expect($fixed->discountFor(15000))->toBe(2000)
            ->and($fixed->discountFor(1500))->toBe(1500)
            ->and($percent->discountFor(15000))->toBe(1500);
    });

    it('is only redeemable while active, in its period and under its limit', function () {
        $v = Voucher::create([
            'code' => 'X', 'discount_type' => 'fixed', 'discount_value' => 500,
            'valid_from' => '2026-12-01', 'valid_until' => '2026-12-31', 'max_uses' => 2,
        ]);
        $day = fn ($d) => CarbonImmutable::parse($d);

        expect($v->isRedeemable($day('2026-12-15')))->toBeTrue()
            ->and($v->rejectionReason($day('2026-11-30')))->toBe(__('This voucher is not valid yet.'))
            ->and($v->rejectionReason($day('2027-01-01')))->toBe(__('This voucher has expired.'));

        // Early morning in Vienna on the first day after the end is already expired.
        expect($v->rejectionReason(Carbon\Carbon::parse('2027-01-01 00:30', 'Europe/Vienna')))->toBe(__('This voucher has expired.'));

        $v->forceFill(['times_used' => 2])->save();
        expect($v->rejectionReason($day('2026-12-15')))->toBe(__('This voucher has already been used up.'));

        $v->forceFill(['times_used' => 0, 'is_active' => false])->save();
        expect($v->rejectionReason($day('2026-12-15')))->toBe(__('This voucher is no longer active.'));
    });
});

describe('school isolation', function () {
    it('cannot edit or delete another school\'s settings', function () {
        $other = School::factory()->create();
        $foreign = foreignRecord(Sport::class, $other, ['name' => 'Fremd']);

        expect(fn () => Livewire::test(Settings\Sports::class)->call('delete', $foreign->id))
            ->toThrow(ModelNotFoundException::class);
        expect(fn () => Livewire::test(Settings\Sports::class)->call('startEditing', $foreign->id))
            ->toThrow(ModelNotFoundException::class);

        expect(Sport::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
    });
});
