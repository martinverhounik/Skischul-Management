<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Staff,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => Role::Owner]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** An invited user who has not set a password yet. */
    public function invited(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'email_verified_at' => null,
            'invited_at' => now(),
        ]);
    }

    public function forSchool(School $school): static
    {
        return $this->state(fn () => ['school_id' => $school->id]);
    }
}
