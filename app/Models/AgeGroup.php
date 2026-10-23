<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * An age band such as "Kids 5–9" (US-04). Bands may overlap.
 *
 * Plan decision: the skill level matters more than the age group, so the
 * age check at booking is a warning the office can override.
 *
 * @property int $id
 * @property string $name
 * @property int $min_age
 * @property int $max_age
 */
class AgeGroup extends Model
{
    use BelongsToSchool, GuardsDeletion;

    protected $fillable = ['name', 'min_age', 'max_age'];

    protected function casts(): array
    {
        return ['min_age' => 'integer', 'max_age' => 'integer'];
    }

    public function includesAge(int $age): bool
    {
        return $age >= $this->min_age && $age <= $this->max_age;
    }

    protected function usages(): array
    {
        // Step 3 adds: price tables.
        return [];
    }
}
