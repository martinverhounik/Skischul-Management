<?php

namespace Database\Seeders;

use App\Actions\Schools\CreateSchoolDefaults;
use App\Domain\Booking\PlaceOrder;
use App\Domain\Pricing\PriceTableForm;
use App\Domain\Pricing\PriceTableValidator;
use App\Domain\Pricing\SavePriceTable;
use App\Models\Instructor;
use App\Models\PriceTable;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        app(CurrentSchool::class)->runAs($school, function () use ($school) {
            $this->priceTables($school);
            $this->people($school);
        });

        $owner = User::factory()->forSchool($school)->owner()->create([
            'name' => 'Martina Owner',
            'email' => 'owner@example.com',
        ]);

        app(CurrentSchool::class)->runAs($school, fn () => $this->bookings($school, $owner));

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

    private function people(School $school): void
    {
        fake()->seed(2026);
        $levels = $school->skillLevels()->pluck('id')->all();
        $accommodations = ['Hotel Alpenhof', 'Pension Edelweiss', 'Gasthof Post', 'Chalet Bergblick', 'Apartment Sonnhang', null];
        $owners = ['Mutter', 'Vater', 'selbst'];

        foreach (range(1, 36) as $i) {
            Student::factory()->create([
                'school_id' => $school->id,
                'skill_level_id' => $levels[$i % count($levels)],
                'date_of_birth' => fake()->dateTimeBetween($i % 5 === 0 ? '-45 years' : '-13 years', $i % 5 === 0 ? '-18 years' : '-4 years')->format('Y-m-d'),
                'primary_phone_owner' => $i % 5 === 0 ? 'selbst' : $owners[$i % 2],
                'accommodation' => $accommodations[$i % count($accommodations)],
                'preferred_language' => ['DE', 'EN', 'NL', 'DE', 'CS'][$i % 5],
                'medical_notes' => $i % 9 === 0 ? 'Nussallergie' : null,
            ]);
        }

        $ski = $school->sports()->where('name', 'Ski')->value('id');
        $board = $school->sports()->where('name', 'Snowboard')->value('id');
        $team = [
            ['Anna', 'Berger', [$ski], ['DE', 'EN'], 'Landesskilehrerin'],
            ['Lukas', 'Huber', [$ski], ['DE', 'EN', 'NL'], 'Skilehrer'],
            ['Sophie', 'Gruber', [$ski], ['DE', 'EN', 'FR'], 'Staatlich geprüft'],
            ['Max', 'Wagner', [$ski, $board], ['DE', 'EN'], 'Landesskilehrer'],
            ['Lena', 'Moser', [$board], ['DE', 'EN', 'IT'], 'Snowboardlehrerin'],
            ['Paul', 'Steiner', [$ski], ['DE'], 'Anwärter'],
        ];
        foreach ($team as $i => [$first, $last, $sports, $languages, $cert]) {
            $instructor = Instructor::factory()->create([
                'school_id' => $school->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'@skischule-demo.test',
                'languages' => $languages,
                'certification_level' => $cert,
                'hourly_rate_cents' => 2200 + 200 * $i,
                'is_active' => $i < 5,
            ]);
            $instructor->sports()->sync($sports);
        }
    }

    /** A busy week from Sunday 10 January 2027: kids courses and private lessons. */
    private function bookings(School $school, User $owner): void
    {
        $kids = PriceTable::query()->where('name', 'Kinderskikurs')->first();
        $private = PriceTable::query()->where('course_type', 'private')->first();
        $ski = $school->sports()->where('name', 'Ski')->value('id');
        $start = CarbonImmutable::parse('2027-01-10');
        $levels = $kids->skillLevels->pluck('id')->all();

        $children = Student::query()->whereIn('skill_level_id', $levels)->get()
            ->filter(fn (Student $s) => $s->ageOn($start) >= 6 && $s->ageOn($start) <= 14)->values();
        $adults = Student::query()->get()->filter(fn (Student $s) => $s->ageOn($start) >= 18)->values();

        $book = fn (string $name, array $items) => app(PlaceOrder::class)->handle($school, $owner, $items, [], [
            'booker_name' => $name, 'email' => Str::slug($name).'@example.com', 'phone' => '+43 664 1234567',
            'street' => 'Dorfstraße', 'house_number' => '7', 'postal_code' => '6370', 'city' => 'Kitzbühel',
            'country' => 'AT', 'payment_method' => 'card',
        ], null);

        foreach ($children->chunk(2) as $i => $pair) {
            $book('Familie '.$pair->first()->last_name, [[
                'price_table_id' => $kids->id, 'sport_id' => $ski, 'timeslot_key' => 'morning',
                'start_date' => $start->toDateString(), 'days' => 5,
                'students' => $pair->map(fn ($s) => ['id' => $s->id])->values()->all(),
            ]]);
        }

        foreach ($adults->take(3)->values() as $i => $adult) {
            $book($adult->fullName(), [[
                'price_table_id' => $private->id, 'sport_id' => $ski,
                'start_date' => $start->addDay()->toDateString(), 'start_time' => ['10:00', '13:30', '14:00'][$i], 'hours' => 2,
                'students' => [['id' => $adult->id]],
            ]]);
        }
    }
}
