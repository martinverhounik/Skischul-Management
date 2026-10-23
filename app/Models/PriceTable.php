<?php

namespace App\Models;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course offer with its rules and price grid (US-07).
 *
 * @property int $id
 * @property CourseType $course_type
 * @property string|null $name
 * @property string|null $description
 * @property int $min_participants
 * @property int $max_participants
 * @property int|null $min_days
 * @property int|null $max_days
 * @property list<int>|null $start_weekdays 0 = Sunday ... 6 = Saturday
 * @property int|null $end_weekday
 * @property int|null $max_hours
 * @property bool $is_active
 */
class PriceTable extends Model
{
    use BelongsToSchool, GuardsDeletion;

    protected $attributes = ['is_active' => true, 'min_participants' => 1, 'max_participants' => 1];

    protected $fillable = [
        'course_type', 'name', 'description', 'min_participants', 'max_participants',
        'min_days', 'max_days', 'start_weekdays', 'end_weekday', 'max_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'course_type' => CourseType::class,
            'start_weekdays' => 'array',
            'min_participants' => 'integer',
            'max_participants' => 'integer',
            'min_days' => 'integer',
            'max_days' => 'integer',
            'end_weekday' => 'integer',
            'max_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Sport, $this> */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)->orderBy('position');
    }

    /** @return BelongsToMany<AgeGroup, $this> */
    public function ageGroups(): BelongsToMany
    {
        return $this->belongsToMany(AgeGroup::class, 'price_table_age_group')->orderBy('min_age');
    }

    /** @return BelongsToMany<SkillLevel, $this> */
    public function skillLevels(): BelongsToMany
    {
        return $this->belongsToMany(SkillLevel::class)->orderBy('position');
    }

    /** @return HasMany<PriceTableTimeOption, $this> */
    public function timeOptions(): HasMany
    {
        return $this->hasMany(PriceTableTimeOption::class);
    }

    /** @return HasMany<PriceTablePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(PriceTablePrice::class);
    }

    /** Name shown everywhere; defaults to "Sport + course type" (US-07). */
    public function displayName(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $sports = $this->sports->pluck('name')->implode(' / ');

        return trim($sports.' '.$this->course_type->label());
    }

    /** @return list<TimeslotKey> */
    public function activeTimeslotKeys(): array
    {
        $order = array_map(fn ($k) => $k->value, TimeslotKey::cases());

        return $this->timeOptions
            ->sortBy(fn ($o) => array_search($o->timeslot_key->value, $order, true))
            ->map(fn ($o) => $o->timeslot_key)
            ->values()
            ->all();
    }

    public function minHoursFor(TimeslotKey $key): ?int
    {
        return $this->timeOptions->firstWhere('timeslot_key', $key)?->min_hours;
    }

    public function priceCell(TimeslotKey $key, int $units): ?PriceTablePrice
    {
        return $this->prices->first(fn ($p) => $p->timeslot_key === $key && $p->units === $units);
    }

    protected function usages(): array
    {
        // Step 5 adds: booking lines. A used price table should be switched off.
        return [];
    }
}
