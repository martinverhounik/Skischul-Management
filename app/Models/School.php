<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One ski school (tenant).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $locale
 * @property string $timezone
 * @property int $week_starts_on 0 = Sunday ... 6 = Saturday
 */
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'locale',
        'timezone',
        'week_starts_on',
    ];

    protected function casts(): array
    {
        return [
            'week_starts_on' => 'integer',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
