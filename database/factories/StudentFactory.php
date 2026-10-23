<?php

namespace Database\Factories;

use App\Models\SkillLevel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-14 years', '-5 years')->format('Y-m-d'),
            'skill_level_id' => fn (array $attributes) => SkillLevel::query()
                ->where('school_id', $attributes['school_id'] ?? null)->value('id')
                ?? SkillLevel::query()->value('id'),
            'primary_phone' => '+43 664 '.fake()->numerify('#######'),
            'primary_phone_owner' => 'Mutter',
            'preferred_language' => 'DE',
        ];
    }
}
