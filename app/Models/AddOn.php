<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * An extra sold with courses, e.g. lunch or rental (US-08).
 * Add-ons used in bookings cannot be deleted, only deactivated.
 *
 * @property int $id
 * @property string $name
 * @property int $price_cents
 * @property bool $is_active
 */
class AddOn extends Model
{
    use BelongsToSchool, GuardsDeletion;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'price_cents', 'is_active'];

    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'is_active' => 'boolean'];
    }

    protected function usages(): array
    {
        // Step 5 adds: bookings.
        return [];
    }
}
