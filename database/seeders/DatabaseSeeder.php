<?php

namespace Database\Seeders;

use App\Actions\Schools\CreateSchoolDefaults;
use App\Domain\Pricing\PriceTableForm;
use App\Domain\Pricing\PriceTableValidator;
use App\Domain\Pricing\SavePriceTable;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
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

        app(CurrentSchool::class)->runAs($school, fn () => $this->priceTables($school));

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

    private function priceTables(School $school): void
    {
        $id = fn ($relation, $name) => (string) $school->{$relation}()->where('name', $name)->value('id');
        $save = function (array $form) use ($school) {
            $clean = app(PriceTableValidator::class)->validate($form, $school->fresh());
            app(SavePriceTable::class)->handle(null, $clean);
        };

        // Kids ski group course: Sunday or Monday start, until Friday.
        $kids = array_replace_recursive(PriceTableForm::blank(), [
            'name' => 'Kinderskikurs',
            'sport_ids' => [$id('sports', 'Ski')],
            'age_group_ids' => [$id('ageGroups', 'Kids')],
            'skill_level_ids' => [$id('skillLevels', 'Grün'), $id('skillLevels', 'Blau'), $id('skillLevels', 'Rot')],
            'min_participants' => '4', 'max_participants' => '8',
            'options' => ['full_day' => ['active' => true], 'morning' => ['active' => true], 'afternoon' => ['active' => true]],
            'min_days' => '1', 'max_days' => '6', 'start_weekdays' => [0, 1], 'end_weekday' => '5',
        ]);
        foreach ([1 => [75, 65, 115], 2 => [145, 125, 220], 3 => [205, 180, 315], 4 => [255, 225, 395], 5 => [295, 260, 455], 6 => [325, 285, 505]] as $d => [$am, $pm, $full]) {
            $kids['prices']['morning'][$d]['price'] = (string) $am;
            $kids['prices']['afternoon'][$d]['price'] = (string) $pm;
            $kids['prices']['full_day'][$d]['price'] = (string) $full;
        }
        $save($kids);

        // Private ski lesson, any age and level.
        $private = array_replace_recursive(PriceTableForm::blank(), [
            'course_type' => 'private',
            'sport_ids' => [$id('sports', 'Ski'), $id('sports', 'Snowboard')],
            'age_group_ids' => $school->ageGroups()->pluck('id')->map(fn ($v) => (string) $v)->all(),
            'skill_level_ids' => $school->skillLevels()->pluck('id')->map(fn ($v) => (string) $v)->all(),
            'min_participants' => '1', 'max_participants' => '4',
            'options' => [
                'full_day' => ['active' => true, 'min_hours' => '4'],
                'morning' => ['active' => true, 'min_hours' => '1'],
                'afternoon' => ['active' => true, 'min_hours' => '1'],
            ],
            'max_hours' => '6',
        ]);
        foreach (range(1, 6) as $h) {
            $private['prices']['morning'][$h] = ['price' => (string) (75 * $h), 'extra' => (string) (20 * $h)];
            $private['prices']['afternoon'][$h] = ['price' => (string) (70 * $h), 'extra' => (string) (20 * $h)];
            $private['prices']['full_day'][$h] = ['price' => (string) (75 * $h + 10), 'extra' => (string) (20 * $h + 5)];
        }
        $save($private);
    }
}
