<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\BelongsToSchool;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A back-office user (US-27, US-31).
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $email
 * @property string|null $password Null until an invited user sets one
 * @property Role $role
 * @property string|null $locale
 * @property bool $is_active
 * @property Carbon|null $invited_at
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    use BelongsToSchool;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['role' => 'staff', 'is_active' => true];

    /**
     * school_id, role and is_active are deliberately not mass assignable:
     * they are only changed through explicit, authorised actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    /** True while an invited user has not yet set a password. */
    public function isPendingInvite(): bool
    {
        return $this->password === null;
    }

    /** Language used for this user's screens and emails. */
    public function preferredLocale(): string
    {
        return $this->locale ?? $this->school?->locale ?? config('app.locale');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
