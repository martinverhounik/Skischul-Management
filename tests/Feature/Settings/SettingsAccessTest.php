<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Livewire\Settings;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

// US-27: global school settings are owner only, checked on the server.

$pages = [
    'general' => ['/settings/general', Settings\General::class],
    'season' => ['/settings/season', Settings\Season::class],
    'times' => ['/settings/times', Settings\Times::class],
    'sports' => ['/settings/sports', Settings\Sports::class],
    'skill levels' => ['/settings/skill-levels', Settings\SkillLevels::class],
    'age groups' => ['/settings/age-groups', Settings\AgeGroups::class],
    'meeting points' => ['/settings/meeting-points', Settings\MeetingPoints::class],
    'add-ons' => ['/settings/add-ons', Settings\AddOns::class],
    'vouchers' => ['/settings/vouchers', Settings\Vouchers::class],
];

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->owner = User::factory()->forSchool($this->school)->owner()->create();
    $this->staff = User::factory()->forSchool($this->school)->create();
});

it('opens for owners', function (string $url) {
    $this->actingAs($this->owner)->get($url)->assertOk();
})->with($pages);

it('is closed to staff, in the browser and for direct Livewire calls', function (string $url, string $component) {
    $this->actingAs($this->staff)->get($url)->assertForbidden();

    $this->signIn($this->staff);
    Livewire::test($component)->assertForbidden();
})->with($pages);

it('shows the settings menu item only to owners', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertSee(route('settings.general'));
    $this->actingAs($this->staff)->get('/dashboard')->assertDontSee(route('settings.general'));
});
