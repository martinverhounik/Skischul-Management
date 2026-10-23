<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Role changes and (de)activation of back-office users (US-31).
 *
 * A school must always keep at least one active owner, otherwise nobody
 * could manage users or settings any more.
 */
class ManageUser
{
    public function changeRole(User $user, Role $role): void
    {
        if ($user->role === Role::Owner && $role !== Role::Owner) {
            $this->ensureAnotherActiveOwner($user);
        }

        $user->role = $role;
        $user->save();
    }

    /**
     * Deactivated users cannot log in, their open sessions end immediately,
     * and they stay in the database so history (e.g. who handled a booking)
     * keeps pointing at them.
     */
    public function deactivate(User $user): void
    {
        if ($user->role === Role::Owner) {
            $this->ensureAnotherActiveOwner($user);
        }

        DB::transaction(function () use ($user) {
            $user->is_active = false;
            $user->setRememberToken(Str::random(60));
            $user->save();

            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });
    }

    public function reactivate(User $user): void
    {
        $user->is_active = true;
        $user->save();
    }

    private function ensureAnotherActiveOwner(User $user): void
    {
        $otherOwners = User::query()
            ->where('school_id', $user->school_id)
            ->where('role', Role::Owner)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();

        if (! $otherOwners) {
            throw ValidationException::withMessages([
                'user' => __('The school needs at least one active owner. Make someone else an owner first.'),
            ]);
        }
    }
}
