<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Livewire\Students\Edit;
use App\Livewire\Students\Index;
use App\Models\School;
use App\Models\SkillLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// US-17 to US-19

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->staff = User::factory()->forSchool($this->school)->create();
    $this->signIn($this->staff);
    $this->blue = SkillLevel::where('name', 'Blau')->first();
});

function fillStudent($component, array $override = []): mixed
{
    $values = array_merge([
        'first_name' => 'Lisa', 'last_name' => 'Brandner', 'date_of_birth' => '2018-03-14',
        'skill_level_id' => (string) test()->blue->id,
        'primary_phone' => '+43 664 1234567', 'primary_phone_owner' => 'Mutter',
    ], $override);

    foreach ($values as $key => $value) {
        $component->set("form.{$key}", $value);
    }

    return $component;
}

it('lets staff add a student with the required fields (US-18)', function () {
    fillStudent(Livewire::test(Edit::class))
        ->set('form.accommodation', 'Hotel Alpenhof')
        ->set('form.medical_notes', 'Nussallergie')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('students.index'));

    $student = Student::firstOrFail();
    expect($student)
        ->fullName()->toBe('Lisa Brandner')
        ->skill_level_id->toBe($this->blue->id)
        ->school_id->toBe($this->school->id)
        ->secondary_phone->toBeNull()
        ->medical_notes->toBe('Nussallergie');
});

it('requires the marked fields', function () {
    Livewire::test(Edit::class)
        ->call('save')
        ->assertHasErrors(['form.first_name', 'form.last_name', 'form.date_of_birth', 'form.skill_level_id', 'form.primary_phone', 'form.primary_phone_owner']);
});

it('needs an owner for a second phone number and refuses a birth date in the future', function () {
    fillStudent(Livewire::test(Edit::class), ['date_of_birth' => now()->addDay()->toDateString()])
        ->set('form.secondary_phone', '+43 660 7654321')
        ->call('save')
        ->assertHasErrors(['form.secondary_phone_owner', 'form.date_of_birth']);
});

it('only accepts this school\'s skill levels', function () {
    $other = School::factory()->create();
    $foreign = new SkillLevel;
    $foreign->forceFill(['school_id' => $other->id, 'name' => 'Fremd'])->save();

    fillStudent(Livewire::test(Edit::class), ['skill_level_id' => (string) $foreign->id])
        ->call('save')
        ->assertHasErrors('form.skill_level_id');
});

it('edits any field (US-19)', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    Livewire::test(Edit::class, ['student' => $student])
        ->assertSet('form.first_name', $student->first_name)
        ->set('form.accommodation', 'Pension Edelweiss')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->fresh()->accommodation)->toBe('Pension Edelweiss');
});

it('archives and restores a student (US-19)', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Archie']);

    Livewire::test(Edit::class, ['student' => $student])->call('archive');
    expect($student->fresh()->isArchived())->toBeTrue();

    Livewire::test(Index::class)->assertDontSee('Archie');
    Livewire::test(Index::class)->set('showArchived', true)->assertSee('Archie');

    Livewire::test(Edit::class, ['student' => $student->fresh()])->call('archive');
    expect($student->fresh()->isArchived())->toBeFalse();
});

it('shows students as sedcards and searches by name, phone and accommodation (US-17)', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Mia', 'last_name' => 'Hofer', 'primary_phone' => '+43 664 555 1212', 'accommodation' => 'Gasthof Post']);
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Jonas', 'last_name' => 'Klein', 'primary_phone' => '+43 660 999 0000']);

    $this->get(route('students.index'))->assertOk()->assertSee('Mia Hofer')->assertSee('Jonas Klein');

    Livewire::test(Index::class)->set('search', 'hofer')->assertSee('Mia Hofer')->assertDontSee('Jonas Klein');
    Livewire::test(Index::class)->set('search', '5551212')->assertSee('Mia Hofer')->assertDontSee('Jonas Klein');
    Livewire::test(Index::class)->set('search', 'gasthof')->assertSee('Mia Hofer')->assertDontSee('Jonas Klein');
    Livewire::test(Index::class)->set('search', 'mia klein')->assertDontSee('Mia Hofer')->assertDontSee('Jonas Klein');
});

it('hints at a possible duplicate', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'first_name' => 'Lisa', 'last_name' => 'Brandner', 'date_of_birth' => '2018-03-14']);

    fillStudent(Livewire::test(Edit::class))->assertSee(__('Possibly already in the list:'));
});

it('works out the age on a given day', function () {
    $student = Student::factory()->make(['date_of_birth' => '2018-03-14']);

    expect($student->ageOn(CarbonImmutable::parse('2026-03-13')))->toBe(7)
        ->and($student->ageOn(CarbonImmutable::parse('2026-03-14')))->toBe(8);
});

it('does not let a skill level be deleted while a student has it (US-30)', function () {
    Student::factory()->create(['school_id' => $this->school->id, 'skill_level_id' => $this->blue->id]);

    expect(fn () => $this->blue->deleteIfUnused())->toThrow(ValidationException::class);
});

it('never shows another school\'s students', function () {
    $other = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($other);
    $foreign = app(CurrentSchool::class)->runAs($other, fn () => Student::factory()->create(['school_id' => $other->id, 'first_name' => 'Fremdling']));

    app(CurrentSchool::class)->set(null);
    $this->actingAs($this->staff)->get(route('students.index'))->assertDontSee('Fremdling');
    $this->actingAs($this->staff)->get(route('students.edit', $foreign->id))->assertNotFound();
});
