<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// US-31: self-service password reset by email.

it('sends a reset link', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/forgot-password', ['email' => 'anna@example.com'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('answers the same way for an unknown email', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', __('If this email address belongs to an account, we have sent a link to reset the password.'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('sends no link to a deactivated user', function () {
    Notification::fake();
    User::factory()->inactive()->create(['email' => 'gone@example.com']);

    $this->post('/forgot-password', ['email' => 'gone@example.com'])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('resets the password with the emailed link', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/forgot-password', ['email' => 'anna@example.com']);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Neues-Passwort-2026',
            'password_confirmation' => 'Neues-Passwort-2026',
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        return true;
    });

    expect(Hash::check('Neues-Passwort-2026', $user->fresh()->password))->toBeTrue();
});

it('refuses a wrong reset token', function () {
    $user = User::factory()->create();

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'Neues-Passwort-2026',
        'password_confirmation' => 'Neues-Passwort-2026',
    ])->assertSessionHasErrors('email');
});

it('requires a password of at least 10 characters with letters and numbers', function () {
    $this->post('/reset-password', [
        'token' => 'x',
        'email' => 'anna@example.com',
        'password' => 'short1',
        'password_confirmation' => 'short1',
    ])->assertSessionHasErrors('password');
});
