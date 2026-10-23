<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /** @return BelongsToMany<PriceTable, $this> */
    public function priceTables(): BelongsToMany
    {
        return $this->belongsToMany(PriceTable::class);
    }

    protected function usages(): array
    {
        return [
            __('Price tables') => fn () => $this->priceTables()->count(),
            __('Instructors') => fn () => $this->belongsToMany(Instructor::class)->count(),
        ];
    }
}
