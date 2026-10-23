<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

// US-27: log in, log out, safe failures, no self-registration.

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee(__('Log in'));
});

it('sends guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/users')->assertRedirect('/login');
});

it('logs in with email and password', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('ignores upper case in the email address', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['email' => 'Anna@Example.com', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

it('gives the same error for a wrong password, an unknown email and a deactivated user', function () {
    User::factory()->create(['email' => 'anna@example.com']);
    User::factory()->inactive()->create(['email' => 'gone@example.com']);

    $attempts = [
        ['email' => 'anna@example.com', 'password' => 'wrong-password'],
        ['email' => 'nobody@example.com', 'password' => 'password'],
        ['email' => 'gone@example.com', 'password' => 'password'],
    ];

    foreach ($attempts as $credentials) {
        $this->post('/login', $credentials)->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }
});

it('does not let an invited user log in before they set a password', function () {
    User::factory()->invited()->create(['email' => 'new@example.com']);

    $this->post('/login', ['email' => 'new@example.com', 'password' => ''])->assertSessionHasErrors('password');
    $this->post('/login', ['email' => 'new@example.com', 'password' => 'anything'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks the login after 5 failed attempts', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'anna@example.com', 'password' => 'wrong']);
    }

    // Even the correct password is refused while locked.
    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    RateLimiter::clear('anna@example.com|127.0.0.1');
});

it('logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('has no self-registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

it('ends a session after 60 minutes of inactivity', function () {
    expect(config('session.lifetime'))->toBe(60)
        ->and(config('session.expire_on_close'))->toBeFalse();
});
