<?php

namespace Database\Seeders;

use App\Actions\Schools\CreateSchoolDefaults;
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
            'season_starts_on' => '2026-12-05',
            'season_ends_on' => '2027-04-11',
        ]);

        app(CreateSchoolDefaults::class)->handle($school);

        $school->closedDays()->create(['date' => '2026-12-24', 'note' => 'Heiligabend']);
        $school->meetingPoints()->create(['name' => 'Talstation Gondel', 'map_url' => null]);
        $school->meetingPoints()->create(['name' => 'Kinderland Mittelstation', 'map_url' => null]);

        $lunch = $school->addOns()->create(['name' => 'Mittagsbetreuung', 'price_cents' => 2200]);
        $school->addOns()->create(['name' => 'Skiverleih Kinder (Tag)', 'price_cents' => 1900]);
        $school->update(['lunch_add_on_id' => $lunch->id]);

        $school->vouchers()->create([
            'code' => 'WINTER10', 'discount_type' => 'percent', 'discount_value' => 1000,
            'valid_until' => '2027-01-31',
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
