<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * A school-defined ability level, sorted from beginner to advanced (US-03).
 *
 * @property int $id
 * @property string $name
 * @property string|null $color hex colour for the dot, e.g. #1f6feb
 * @property int $position
 */
class SkillLevel extends Model
{
    use BelongsToSchool, GuardsDeletion;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['position' => 0];

    protected $fillable = ['name', 'color', 'position'];

    protected function usages(): array
    {
        // Step 3 adds: price tables. Step 4 adds: students.
        return [];
    }
}
