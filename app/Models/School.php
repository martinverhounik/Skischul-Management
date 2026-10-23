<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One ski school (tenant) and its global settings.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $locale
 * @property string $timezone
 * @property int $week_starts_on 0 = Sunday ... 6 = Saturday
 * @property string $currency
 * @property Carbon|null $season_starts_on
 * @property Carbon|null $season_ends_on
 * @property bool $enforce_full_day_price
 * @property int|null $lunch_add_on_id
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['locale' => 'de', 'timezone' => 'Europe/Vienna', 'week_starts_on' => 0, 'currency' => 'EUR', 'enforce_full_day_price' => true];

    protected $fillable = [
        'name',
        'slug',
        'locale',
        'timezone',
        'week_starts_on',
        'currency',
        'season_starts_on',
        'season_ends_on',
        'enforce_full_day_price',
        'lunch_add_on_id',
    ];

    protected function casts(): array
    {
        return [
            'week_starts_on' => 'integer',
            'season_starts_on' => 'date',
            'season_ends_on' => 'date',
            'enforce_full_day_price' => 'boolean',
        ];
    }

    /*
     * Relations below query by school_id explicitly, so they also work in
     * console commands where no "current school" is set.
     */

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<ClosedDay, $this> */
    public function closedDays(): HasMany
    {
        return $this->hasMany(ClosedDay::class)->orderBy('date');
    }

    /** @return HasMany<Sport, $this> */
    public function sports(): HasMany
    {
        return $this->hasMany(Sport::class)->orderBy('position')->orderBy('name');
    }

    /** @return HasMany<SkillLevel, $this> */
    public function skillLevels(): HasMany
    {
        return $this->hasMany(SkillLevel::class)->orderBy('position');
    }

    /** @return HasMany<AgeGroup, $this> */
    public function ageGroups(): HasMany
    {
        return $this->hasMany(AgeGroup::class)->orderBy('min_age')->orderBy('max_age');
    }

    /** @return HasMany<MeetingPoint, $this> */
    public function meetingPoints(): HasMany
    {
        return $this->hasMany(MeetingPoint::class)->orderBy('name');
    }

    /** @return HasMany<Timeslot, $this> */
    public function timeslots(): HasMany
    {
        return $this->hasMany(Timeslot::class);
    }

    /** @return HasMany<AddOn, $this> */
    public function addOns(): HasMany
    {
        return $this->hasMany(AddOn::class)->orderBy('name');
    }

    /** @return HasMany<Voucher, $this> */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class)->orderBy('code');
    }

    /** @return BelongsTo<AddOn, $this> */
    public function lunchAddOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class, 'lunch_add_on_id');
    }

    public function timeslot(TimeslotKey $key): ?Timeslot
    {
        return $this->timeslots->firstWhere('key', $key);
    }

    public function hasSeason(): bool
    {
        return $this->season_starts_on !== null && $this->season_ends_on !== null;
    }

    /** "Today" in the school's own time zone. */
    public function today(): Carbon
    {
        return Carbon::now($this->timezone)->startOfDay();
    }
}
