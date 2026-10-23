<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Enums\TimeslotKey;
use App\Livewire\Settings\PriceTables\Edit;
use App\Livewire\Settings\PriceTables\Index;
use App\Models\AgeGroup;
use App\Models\PriceTable;
use App\Models\School;
use App\Models\SkillLevel;
use App\Models\Sport;
use App\Models\User;
use App\Support\CurrentSchool;
use Livewire\Livewire;

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->owner = User::factory()->forSchool($this->school)->owner()->create();
    $this->staff = User::factory()->forSchool($this->school)->create();
    $this->signIn($this->owner);
});

function fillGroupCourse($component): mixed
{
    $component
        ->set('form.course_type', 'group')
        ->set('form.sport_ids', [(string) Sport::where('name', 'Ski')->value('id')])
        ->set('form.age_group_ids', [(string) AgeGroup::where('name', 'Kids')->value('id')])
        ->set('form.skill_level_ids', [(string) SkillLevel::where('name', 'Grün')->value('id')])
        ->set('form.options.full_day.active', false)
        ->set('form.min_days', '1')
        ->set('form.max_days', '3');

    foreach (range(1, 3) as $d) {
        $component->set("form.prices.morning.{$d}.price", (string) (60 * $d))
            ->set("form.prices.afternoon.{$d}.price", (string) (55 * $d));
    }

    return $component;
}

it('adds a group course through the screen', function () {
    fillGroupCourse(Livewire::test(Edit::class))
        ->set('form.name', 'Kinderkurs')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('settings.price-tables'));

    $table = PriceTable::firstOrFail();
    expect($table->displayName())->toBe('Kinderkurs')
        ->and($table->prices)->toHaveCount(6)
        ->and($table->priceCell(TimeslotKey::Afternoon, 2)->price_cents)->toBe(11000);
});

it('shows errors next to the fields', function () {
    Livewire::test(Edit::class)
        ->set('form.sport_ids', [])
        ->call('save')
        ->assertHasErrors(['form.sport_ids', 'form.age_group_ids', 'form.skill_level_ids', 'form.prices.morning.1.price'])
        ->assertSee(__('Please enter a price.'));
});

it('changes the grid when the course type changes', function () {
    Livewire::test(Edit::class)
        ->assertSee(__('Days'))
        ->assertDontSee(__('Each further'))
        ->set('form.course_type', 'private')
        ->assertSee(__('Hours'))
        ->assertSee(__('Each further'))
        ->assertSee(__('Minimum hours'));
});

it('opens an existing price table for editing and saves changes', function () {
    fillGroupCourse(Livewire::test(Edit::class))->call('save');
    $table = PriceTable::firstOrFail();

    $this->get(route('settings.price-tables.edit', $table))->assertOk()->assertSee('60,00');

    Livewire::test(Edit::class, ['priceTable' => $table])
        ->assertSet('form.prices.morning.1.price', '60,00')
        ->set('form.prices.morning.1.price', '65')
        ->call('save')
        ->assertHasNoErrors();

    expect($table->fresh()->priceCell(TimeslotKey::Morning, 1)->price_cents)->toBe(6500)
        ->and(PriceTable::count())->toBe(1);
});

it('lists, switches off and deletes price tables', function () {
    fillGroupCourse(Livewire::test(Edit::class))->call('save');
    $table = PriceTable::firstOrFail();

    $this->get(route('settings.price-tables'))->assertOk()->assertSee($table->displayName());

    Livewire::test(Index::class)->call('toggleActive', $table->id);
    expect($table->fresh()->is_active)->toBeFalse();

    Livewire::test(Index::class)->call('delete', $table->id);
    expect(PriceTable::count())->toBe(0);
});

it('is owner only (US-27)', function () {
    $this->actingAs($this->staff)->get(route('settings.price-tables'))->assertForbidden();
    $this->actingAs($this->staff)->get(route('settings.price-tables.create'))->assertForbidden();
});

it('does not open another school\'s price table from a link', function () {
    $other = School::factory()->create();
    $foreign = new PriceTable;
    $foreign->forceFill(['school_id' => $other->id, 'course_type' => 'group', 'min_days' => 1, 'max_days' => 1])->save();

    // Start the request with no school set, exactly like a real browser request,
    // so this proves the middleware sets the school before the link is resolved.
    app(CurrentSchool::class)->set(null);

    $this->actingAs($this->owner)->get(route('settings.price-tables.edit', $foreign->id))->assertNotFound();
});
