<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Domain\Pricing\PriceTableForm;
use App\Domain\Pricing\PriceTableValidator;
use App\Domain\Pricing\SavePriceTable;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

/**
 * Shared fixture for booking tests: a school with a season (5 Dec 2026 –
 * 11 Apr 2027), Christmas Eve closed, a kids ski group course
 * (Sun/Mon start, ends Friday, 1–6 days, Grün/Blau/Rot, Kids 6–14) and a
 * private lesson (1–6 hours, full day from 4 hours).
 */
function bookingSchool(): array
{
    $school = School::factory()->create(['season_starts_on' => '2026-12-05', 'season_ends_on' => '2027-04-11']);
    app(CreateSchoolDefaults::class)->handle($school);
    $school->closedDays()->create(['date' => '2026-12-24']);
    $owner = User::factory()->forSchool($school)->owner()->create();
    test()->signIn($owner);

    $id = fn ($relation, $name) => (string) $school->{$relation}()->where('name', $name)->value('id');
    $save = fn (array $form) => app(SavePriceTable::class)->handle(null, app(PriceTableValidator::class)->validate($form, $school->fresh()));

    $kids = array_replace_recursive(PriceTableForm::blank(), [
        'name' => 'Kinderskikurs',
        'sport_ids' => [$id('sports', 'Ski')],
        'age_group_ids' => [$id('ageGroups', 'Kids')],
        'skill_level_ids' => [$id('skillLevels', 'Grün'), $id('skillLevels', 'Blau'), $id('skillLevels', 'Rot')],
        'options' => ['full_day' => ['active' => true], 'morning' => ['active' => true], 'afternoon' => ['active' => true]],
        'min_days' => '1', 'max_days' => '6', 'start_weekdays' => [0, 1], 'end_weekday' => '5',
    ]);
    foreach (range(1, 6) as $d) {
        $kids['prices']['morning'][$d]['price'] = (string) (50 * $d);
        $kids['prices']['afternoon'][$d]['price'] = (string) (45 * $d);
        $kids['prices']['full_day'][$d]['price'] = (string) (80 * $d);
    }

    $private = array_replace_recursive(PriceTableForm::blank(), [
        'course_type' => 'private', 'name' => 'Privatstunde',
        'sport_ids' => [$id('sports', 'Ski'), $id('sports', 'Snowboard')],
        'age_group_ids' => $school->ageGroups()->pluck('id')->map(fn ($v) => (string) $v)->all(),
        'skill_level_ids' => $school->skillLevels()->pluck('id')->map(fn ($v) => (string) $v)->all(),
        'options' => [
            'full_day' => ['active' => true, 'min_hours' => '4'],
            'morning' => ['active' => true, 'min_hours' => '1'],
            'afternoon' => ['active' => true, 'min_hours' => '1'],
        ],
        'max_hours' => '6',
    ]);
    foreach (range(1, 6) as $h) {
        $private['prices']['morning'][$h] = ['price' => (string) (70 * $h), 'extra' => (string) (20 * $h)];
        $private['prices']['afternoon'][$h] = ['price' => (string) (65 * $h), 'extra' => (string) (20 * $h)];
        $private['prices']['full_day'][$h] = ['price' => (string) (70 * $h + 10), 'extra' => (string) (20 * $h + 5)];
    }

    $blue = $school->skillLevels()->where('name', 'Blau')->first();

    return [
        'school' => $school->fresh(),
        'owner' => $owner,
        'kids' => $save($kids),
        'private' => $save($private),
        'ski' => $school->sports()->where('name', 'Ski')->first(),
        'blue' => $blue,
        'black' => $school->skillLevels()->where('name', 'Schwarz')->first(),
        // 8 years old at Christmas 2026
        'lisa' => Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Lisa', 'last_name' => 'Brandner', 'date_of_birth' => '2018-05-01', 'skill_level_id' => $blue->id]),
        'tom' => Student::factory()->create(['school_id' => $school->id, 'first_name' => 'Tom', 'last_name' => 'Brandner', 'date_of_birth' => '2016-02-10', 'skill_level_id' => $blue->id]),
    ];
}

function groupItem(array $f, array $override = []): array
{
    return array_merge([
        'price_table_id' => $f['kids']->id,
        'sport_id' => $f['ski']->id,
        'timeslot_key' => 'morning',
        'start_date' => '2027-01-10', // a Sunday
        'days' => 5,
        'students' => [['id' => $f['lisa']->id]],
    ], $override);
}

function privateItem(array $f, array $override = []): array
{
    return array_merge([
        'price_table_id' => $f['private']->id,
        'sport_id' => $f['ski']->id,
        'start_date' => '2027-01-12',
        'start_time' => '10:00',
        'hours' => 2,
        'students' => [['id' => $f['lisa']->id]],
    ], $override);
}
