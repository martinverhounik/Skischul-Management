<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Creates a user without a password and emails them a link to set one
 * (US-31). There is no open self-registration (US-27).
 */
class InviteUser
{
    public function handle(School $school, string $name, string $email, Role $role): User
    {
        $user = DB::transaction(function () use ($school, $name, $email, $role) {
            $user = new User(['name' => $name, 'email' => mb_strtolower($email)]);
            $user->school_id = $school->id;
            $user->role = $role;
            $user->is_active = true;
            $user->invited_at = now();
            $user->save();

            return $user;
        });

        $this->sendInvitation($user);

        return $user;
    }

    /** Sends (or re-sends) the set-password link. Older links stop working. */
    public function sendInvitation(User $user): void
    {
        $token = Password::broker('invites')->createToken($user);

        $user->notify(
            (new InvitationNotification($token, $user->school->name))->locale($user->preferredLocale())
        );

        $user->forceFill(['invited_at' => now()])->save();
    }
}
