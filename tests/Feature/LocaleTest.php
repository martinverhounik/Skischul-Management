<?php

use App\Livewire\Account;
use App\Models\School;
use App\Models\User;
use Livewire\Livewire;

// Plan question 14: German and English, switchable per user.

it('shows German by default', function () {
    $this->get('/login')->assertSee('Anmelden');
});

it('lets guests switch the language on the login page', function () {
    $this->get('/login?lang=en')->assertSee('Log in');
    $this->get('/login')->assertSee('Log in');
});

it('uses the language the user chose', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)->get('/dashboard')->assertSee('Log out');
});

it('falls back to the school language', function () {
    $school = School::factory()->create(['locale' => 'en']);
    $user = User::factory()->forSchool($school)->create(['locale' => null]);

    $this->actingAs($user)->get('/dashboard')->assertSee('Log out');
});

it('lets users change their own name and language', function () {
    $user = User::factory()->create(['locale' => 'de']);
    $this->signIn($user);

    Livewire::test(Account::class)
        ->set('name', 'Neuer Name')
        ->set('locale', 'en')
        ->call('saveProfile')
        ->assertHasNoErrors();

    expect($user->fresh())->name->toBe('Neuer Name')->locale->toBe('en');
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create();
    $this->signIn($user);

    Livewire::test(Account::class)
        ->set('current_password', 'wrong')
        ->set('password', 'Firnschnee-2026')
        ->set('password_confirmation', 'Firnschnee-2026')
        ->call('savePassword')
        ->assertHasErrors('current_password');

    Livewire::test(Account::class)
        ->set('current_password', 'password')
        ->set('password', 'Firnschnee-2026')
        ->set('password_confirmation', 'Firnschnee-2026')
        ->call('savePassword')
        ->assertHasNoErrors();
});
