<?php

use App\Enums\Role;
use App\Livewire\Users\Index;
use App\Models\School;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// US-27 roles and US-31 account management.

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->owner = User::factory()->forSchool($this->school)->owner()->create(['name' => 'Olga Owner']);
    $this->staff = User::factory()->forSchool($this->school)->create(['name' => 'Sam Staff']);
});

describe('access', function () {
    it('lets owners open the users page', function () {
        $this->actingAs($this->owner)->get('/users')->assertOk()->assertSee('Sam Staff');
    });

    it('blocks staff on the server, not just in the menu', function () {
        $this->actingAs($this->staff)->get('/users')->assertForbidden();

        $this->signIn($this->staff);
        Livewire::test(Index::class)->assertForbidden();
    });

    it('hides the users menu item from staff', function () {
        $this->actingAs($this->staff)->get('/dashboard')->assertOk()->assertDontSee('href="'.route('users.index').'"', false);
        $this->actingAs($this->owner)->get('/dashboard')->assertSee('href="'.route('users.index').'"', false);
    });
});

describe('inviting', function () {
    it('invites a user with a role', function () {
        Notification::fake();
        $this->signIn($this->owner);

        Livewire::test(Index::class)
            ->set('name', 'Nina Neu')
            ->set('email', 'nina@example.com')
            ->set('role', 'owner')
            ->call('invite')
            ->assertHasNoErrors();

        $nina = User::where('email', 'nina@example.com')->firstOrFail();
        expect($nina->school_id)->toBe($this->school->id)
            ->and($nina->role)->toBe(Role::Owner)
            ->and($nina->isPendingInvite())->toBeTrue();
        Notification::assertSentTo($nina, InvitationNotification::class);
    });

    it('validates the invitation form', function () {
        $this->signIn($this->owner);

        Livewire::test(Index::class)
            ->set('name', '')
            ->set('email', $this->staff->email)
            ->set('role', 'superhero')
            ->call('invite')
            ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'role']);
    });

    it('resends an invitation', function () {
        Notification::fake();
        $invited = User::factory()->forSchool($this->school)->invited()->create();
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('resendInvite', $invited->id);

        Notification::assertSentTo($invited, InvitationNotification::class);
    });
});

describe('roles', function () {
    it('changes a role', function () {
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('changeRole', $this->staff->id, 'owner');

        expect($this->staff->fresh()->role)->toBe(Role::Owner);
    });

    it('never leaves a school without an active owner', function () {
        $this->signIn($this->owner);

        Livewire::test(Index::class)
            ->call('changeRole', $this->owner->id, 'staff')
            ->assertHasErrors('user');

        expect($this->owner->fresh()->role)->toBe(Role::Owner);
    });

    it('lets an owner step down once there is another owner', function () {
        $this->staff->forceFill(['role' => Role::Owner])->save();
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('changeRole', $this->owner->id, 'staff')->assertHasNoErrors();

        expect($this->owner->fresh()->role)->toBe(Role::Staff);
    });

    it('lets only owners reach owner-only areas', function () {
        expect($this->owner->can('admin'))->toBeTrue()
            ->and($this->staff->can('admin'))->toBeFalse();
    });
});

describe('deactivating', function () {
    it('deactivates a user, ends their sessions and keeps the record', function () {
        DB::table('sessions')->insert([
            'id' => 'abc', 'user_id' => $this->staff->id, 'ip_address' => '1.2.3.4',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => time(),
        ]);
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('deactivate', $this->staff->id)->assertHasNoErrors();

        expect($this->staff->fresh()->is_active)->toBeFalse()
            ->and(User::find($this->staff->id))->not->toBeNull()
            ->and(DB::table('sessions')->where('user_id', $this->staff->id)->count())->toBe(0);
    });

    it('logs out a deactivated user on their next click', function () {
        $this->actingAs($this->staff)->get('/dashboard')->assertOk();

        $this->staff->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('does not let owners deactivate themselves', function () {
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('deactivate', $this->owner->id)->assertForbidden();

        expect($this->owner->fresh()->is_active)->toBeTrue();
    });

    it('reactivates a user', function () {
        $this->staff->forceFill(['is_active' => false])->save();
        $this->signIn($this->owner);

        Livewire::test(Index::class)->call('reactivate', $this->staff->id);

        expect($this->staff->fresh()->is_active)->toBeTrue();
    });
});
