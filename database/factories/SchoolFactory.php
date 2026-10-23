<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<School> */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Skischule '.fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'locale' => 'de',
            'timezone' => 'Europe/Vienna',
            'week_starts_on' => 0,
        ];
    }
}
