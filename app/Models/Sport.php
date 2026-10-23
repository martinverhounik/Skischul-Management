<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * A sport the school offers, e.g. Ski, Snowboard (US-02).
 *
 * @property int $id
 * @property string $name
 * @property int $position
 */
class Sport extends Model
{
    use BelongsToSchool, GuardsDeletion;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['position' => 0];

    protected $fillable = ['name', 'position'];

    protected function usages(): array
    {
        // Step 3 adds: price tables.
        return [];
    }
}
