<?php

use App\Actions\Users\InviteUser;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\Notification;

// US-31: invited users set their own password.

function inviteSomeone(): array
{
    Notification::fake();
    $school = School::factory()->create(['name' => 'Skischule Test']);
    $user = app(InviteUser::class)->handle($school, 'Lena Neu', 'Lena@Example.com', Role::Staff);

    $token = null;
    Notification::assertSentTo($user, InvitationNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return $n->schoolName === 'Skischule Test';
    });

    return [$user, $token];
}

it('creates the invited user without a password, in lower case, with the chosen role', function () {
    [$user] = inviteSomeone();

    expect($user->email)->toBe('lena@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe(Role::Staff)
        ->and($user->invited_at)->not->toBeNull()
        ->and($user->isPendingInvite())->toBeTrue();
});

it('lets the invited user set a password and logs them in', function () {
    [$user, $token] = inviteSomeone();

    $this->get("/invitation/{$token}?email={$user->email}")->assertOk()->assertSee('Lena Neu');

    $this->post('/invitation', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Pulverschnee-2026',
        'password_confirmation' => 'Pulverschnee-2026',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->isPendingInvite())->toBeFalse();
});

it('can only be used once', function () {
    [$user, $token] = inviteSomeone();
    $data = ['token' => $token, 'email' => $user->email, 'password' => 'Pulverschnee-2026', 'password_confirmation' => 'Pulverschnee-2026'];

    $this->post('/invitation', $data);
    $this->post('/logout');

    $this->get("/invitation/{$token}?email={$user->email}")->assertSee(__('Link not valid'));
    $this->post('/invitation', $data)->assertSessionHasErrors('email');
});

it('expires after 72 hours', function () {
    [$user, $token] = inviteSomeone();

    $this->travel(73)->hours();

    $this->get("/invitation/{$token}?email={$user->email}")->assertSee(__('Link not valid'));
});

it('rejects a wrong token', function () {
    [$user] = inviteSomeone();

    $this->get("/invitation/wrong-token?email={$user->email}")->assertSee(__('Link not valid'));
    $this->post('/invitation', [
        'token' => 'wrong-token', 'email' => $user->email,
        'password' => 'Pulverschnee-2026', 'password_confirmation' => 'Pulverschnee-2026',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('does not work for a user who was deactivated after being invited', function () {
    [$user, $token] = inviteSomeone();
    $user->forceFill(['is_active' => false])->save();

    $this->get("/invitation/{$token}?email={$user->email}")->assertSee(__('Link not valid'));
});

it('creates a school with its first owner from the command line', function () {
    Notification::fake();

    $this->artisan('school:create', ['name' => 'Skischule Alpbach', 'email' => 'chef@example.com', 'owner' => 'Chris Chef'])
        ->assertSuccessful();

    $owner = User::where('email', 'chef@example.com')->firstOrFail();
    expect($owner->role)->toBe(Role::Owner)
        ->and($owner->school->name)->toBe('Skischule Alpbach');
    Notification::assertSentTo($owner, InvitationNotification::class);
});
