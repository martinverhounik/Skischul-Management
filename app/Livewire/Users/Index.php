<?php

namespace App\Livewire\Users;

use App\Actions\Users\InviteUser;
use App\Actions\Users\ManageUser;
use App\Enums\Role;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Users and accounts page (US-31). Owners only: the route checks it and
 * every action checks it again, because Livewire actions can be called
 * directly.
 */
class Index extends Component
{
    public bool $showInvite = false;

    /** Confirmation message shown above the table. */
    public ?string $status = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'staff';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function invite(InviteUser $inviteUser, CurrentSchool $currentSchool): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ], attributes: [
            'name' => __('Name'),
            'email' => __('Email'),
            'role' => __('Role'),
        ]);

        $user = $inviteUser->handle($currentSchool->get(), $validated['name'], $validated['email'], Role::from($validated['role']));

        $this->reset('name', 'email', 'role', 'showInvite');
        $this->status = (__('Invitation sent to :email.', ['email' => $user->email]));
        unset($this->users);
    }

    public function resendInvite(int $userId, InviteUser $inviteUser): void
    {
        $user = $this->findUser($userId);
        $this->authorize('update', $user);

        if (! $user->isPendingInvite()) {
            return;
        }

        $inviteUser->sendInvitation($user);
        $this->status = (__('Invitation sent to :email.', ['email' => $user->email]));
    }

    public function changeRole(int $userId, string $role, ManageUser $manageUser): void
    {
        $user = $this->findUser($userId);
        $this->authorize('update', $user);

        $newRole = Role::tryFrom($role);
        if (! $newRole) {
            return;
        }

        $this->runGuarded(fn () => $manageUser->changeRole($user, $newRole));
        $this->status = (__(':name is now :role.', ['name' => $user->name, 'role' => $newRole->label()]));
    }

    public function deactivate(int $userId, ManageUser $manageUser): void
    {
        $user = $this->findUser($userId);
        $this->authorize('deactivate', $user);

        $this->runGuarded(fn () => $manageUser->deactivate($user));
        $this->status = (__(':name can no longer log in.', ['name' => $user->name]));
    }

    public function reactivate(int $userId, ManageUser $manageUser): void
    {
        $user = $this->findUser($userId);
        $this->authorize('update', $user);

        $manageUser->reactivate($user);
        $this->status = (__(':name can log in again.', ['name' => $user->name]));
        unset($this->users);
    }

    public function render()
    {
        return view('livewire.users.index', [
            'roles' => Role::options(),
        ])->title(__('Users'));
    }

    /** Scoped to the current school by BelongsToSchool, so other schools' users 404. */
    private function findUser(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    private function runGuarded(callable $action): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            unset($this->users);
            throw $e;
        }

        unset($this->users);
    }
}
