<?php

namespace App\Actions\Schools;

use App\Enums\TimeslotKey;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Gives a new school a sensible starting point the owner can then edit:
 * the five timeslots, two sports, four skill levels and three age groups.
 * Only fills what is still empty, so it is safe to run twice.
 */
class CreateSchoolDefaults
{
    public function handle(School $school): void
    {
        $de = $school->locale === 'de';

        DB::transaction(function () use ($school, $de) {
            foreach (TimeslotKey::cases() as $key) {
                [$start, $end] = $key->defaultTimes();
                $school->timeslots()->firstOrCreate(
                    ['key' => $key],
                    ['starts_at' => $start, 'ends_at' => $end],
                );
            }

            if (! $school->sports()->exists()) {
                foreach ($de ? ['Ski', 'Snowboard'] : ['Skiing', 'Snowboarding'] as $i => $name) {
                    $school->sports()->create(['name' => $name, 'position' => $i]);
                }
            }

            if (! $school->skillLevels()->exists()) {
                $levels = $de
                    ? ['Grün' => '#2e7d32', 'Blau' => '#1f5fbf', 'Rot' => '#bb0909', 'Schwarz' => '#171920']
                    : ['Green' => '#2e7d32', 'Blue' => '#1f5fbf', 'Red' => '#bb0909', 'Black' => '#171920'];
                $i = 0;
                foreach ($levels as $name => $color) {
                    $school->skillLevels()->create(['name' => $name, 'color' => $color, 'position' => $i++]);
                }
            }

            if (! $school->ageGroups()->exists()) {
                $groups = $de
                    ? [['Minis', 3, 5], ['Kids', 6, 14], ['Erwachsene', 15, 99]]
                    : [['Minis', 3, 5], ['Kids', 6, 14], ['Adults', 15, 99]];
                foreach ($groups as [$name, $min, $max]) {
                    $school->ageGroups()->create(['name' => $name, 'min_age' => $min, 'max_age' => $max]);
                }
            }
        });
    }
}
