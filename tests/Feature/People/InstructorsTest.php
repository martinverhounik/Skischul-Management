<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Livewire\Instructors\Edit;
use App\Livewire\Instructors\Index;
use App\Models\Instructor;
use App\Models\School;
use App\Models\Sport;
use App\Models\User;
use App\Rules\Iban;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// US-20, US-21, US-27 (financial data owner only)

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->owner = User::factory()->forSchool($this->school)->owner()->create();
    $this->staff = User::factory()->forSchool($this->school)->create();
    $this->signIn($this->owner);
});

function fillInstructor($component, array $override = []): mixed
{
    $values = array_merge([
        'first_name' => 'Anna', 'last_name' => 'Berger', 'date_of_birth' => '1995-02-01',
        'email' => 'Anna@Example.com', 'phone' => '+43 676 1234567',
        'iban' => 'at61 1904 3002 3457 3201', 'social_security_number' => '1234 010195',
    ], $override);

    foreach ($values as $key => $value) {
        $component->set("form.{$key}", $value);
    }

    return $component;
}

it('adds an instructor with required and optional fields (US-20)', function () {
    $ski = Sport::where('name', 'Ski')->first();

    fillInstructor(Livewire::test(Edit::class))
        ->set('form.languages', ['DE', 'NL'])
        ->set('form.sport_ids', [(string) $ski->id])
        ->set('form.hourly_rate', '24,50')
        ->set('form.bic', 'bkauatww')
        ->set('form.certification_level', 'Landesskilehrerin')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('instructors.index'));

    $anna = Instructor::firstOrFail();
    expect($anna)
        ->email->toBe('anna@example.com')
        ->iban->toBe('AT611904300234573201')
        ->bic->toBe('BKAUATWW')
        ->hourly_rate_cents->toBe(2450)
        ->languages->toBe(['DE', 'NL'])
        ->and($anna->teaches($ski))->toBeTrue();
});

it('validates all mandatory fields (US-20, US-30)', function () {
    Livewire::test(Edit::class)
        ->call('save')
        ->assertHasErrors(['form.first_name', 'form.last_name', 'form.date_of_birth', 'form.email', 'form.phone', 'form.iban', 'form.social_security_number']);
});

it('catches a mistyped IBAN', function () {
    fillInstructor(Livewire::test(Edit::class), ['iban' => 'AT61 1904 3002 3457 3202'])
        ->call('save')
        ->assertHasErrors('form.iban');

    expect(Iban::isValid('DE89 3704 0044 0532 0130 00'))->toBeTrue()
        ->and(Iban::isValid('DE89 3704 0044 0532 0130 01'))->toBeFalse()
        ->and(Iban::mask('AT611904300234573201'))->toBe('AT61 •••• •••• 3201');
});

it('checks the IBAN as soon as it is typed', function () {
    Livewire::test(Edit::class)
        ->set('form.iban', 'AT61 1904 3002 3457 3202')
        ->assertHasErrors('form.iban')
        ->set('form.iban', 'AT61 1904 3002 3457 3201')
        ->assertHasNoErrors('form.iban');
});

it('stores IBAN and social security number encrypted', function () {
    fillInstructor(Livewire::test(Edit::class))->call('save');

    $row = DB::table('instructors')->first();
    expect($row->iban)->not->toContain('AT61')
        ->and($row->social_security_number)->not->toContain('1234');
});

it('edits and deactivates an instructor (US-21)', function () {
    $instructor = Instructor::factory()->create(['school_id' => $this->school->id]);

    Livewire::test(Edit::class, ['instructor' => $instructor])
        ->assertSet('form.iban', 'AT61 1904 3002 3457 3201')
        ->set('form.phone', '+43 699 1111111')
        ->set('form.is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($instructor->fresh())->phone->toBe('+43 699 1111111')->is_active->toBeFalse();
});

it('hides inactive instructors by default (US-21)', function () {
    Instructor::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Aktiv']);
    Instructor::factory()->inactive()->create(['school_id' => $this->school->id, 'first_name' => 'Pausiert']);

    Livewire::test(Index::class)->assertSee('Aktiv')->assertDontSee('Pausiert')
        ->set('showInactive', true)->assertSee('Pausiert');
});

it('lets staff see the list but no financial data and no editing (US-27)', function () {
    $instructor = Instructor::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Lukas', 'hourly_rate_cents' => 2500]);

    $this->actingAs($this->staff)->get(route('instructors.index'))
        ->assertOk()
        ->assertSee('Lukas')
        ->assertDontSee('AT61')
        ->assertDontSee('25,00')
        ->assertDontSee(route('instructors.edit', $instructor));

    $this->actingAs($this->staff)->get(route('instructors.create'))->assertForbidden();
    $this->actingAs($this->staff)->get(route('instructors.edit', $instructor))->assertForbidden();

    $this->signIn($this->staff);
    Livewire::test(Edit::class)->assertForbidden();
    Livewire::test(Index::class)->call('toggleActive', $instructor->id)->assertForbidden();
    expect($instructor->fresh()->is_active)->toBeTrue();
});

it('shows owners a masked IBAN in the list', function () {
    Instructor::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->owner)->get(route('instructors.index'))
        ->assertSee('AT61 •••• •••• 3201')
        ->assertDontSee('AT611904300234573201');
});

it('never puts the IBAN into the list page data', function () {
    Instructor::factory()->create(['school_id' => $this->school->id]);

    expect(Instructor::first()->toArray())->not->toHaveKeys(['iban', 'social_security_number', 'hourly_rate_cents']);
});

it('keeps instructor emails unique within a school', function () {
    Instructor::factory()->create(['school_id' => $this->school->id, 'email' => 'anna@example.com']);

    fillInstructor(Livewire::test(Edit::class))->call('save')->assertHasErrors('form.email');
});
