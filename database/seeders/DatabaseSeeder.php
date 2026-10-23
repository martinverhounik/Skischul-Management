<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development only. Never run this on production.
 *
 * Logins (password for both: "password"):
 *   owner@example.com  – owner / administrator
 *   staff@example.com  – staff
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::create([
            'name' => 'Skischule Demo',
            'slug' => 'demo',
            'locale' => 'de',
            'timezone' => 'Europe/Vienna',
            'week_starts_on' => 0,
        ]);

        User::factory()->forSchool($school)->owner()->create([
            'name' => 'Martina Owner',
            'email' => 'owner@example.com',
        ]);

        User::factory()->forSchool($school)->create([
            'name' => 'Stefan Staff',
            'email' => 'staff@example.com',
        ]);

        User::factory()->forSchool($school)->invited()->create([
            'name' => 'Ines Invited',
            'email' => 'invited@example.com',
        ]);
    }
}
