<?php

namespace Database\Factories;

use App\Models\Instructor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Instructor> */
class InstructorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-19 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+43 676 '.fake()->numerify('#######'),
            // A valid test IBAN (Austrian example from the IBAN registry).
            'iban' => 'AT611904300234573201',
            'social_security_number' => fake()->numerify('#### ######'),
            'languages' => ['DE', 'EN'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
