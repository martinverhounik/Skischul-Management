<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\InstructorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A ski instructor (US-20, US-21).
 *
 * IBAN and social security number are stored encrypted; together with the
 * hourly rate they are shown to owners only (US-27). Inactive instructors
 * are left out of assignment and calendar filters by default.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $date_of_birth
 * @property string $email
 * @property string $phone
 * @property string $iban
 * @property string $social_security_number
 * @property string|null $bic
 * @property int|null $hourly_rate_cents
 * @property string|null $certification_level
 * @property list<string>|null $languages
 * @property string|null $notes
 * @property bool $is_active
 */
class Instructor extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<InstructorFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 'email', 'phone',
        'iban', 'social_security_number', 'bic', 'hourly_rate_cents',
        'certification_level', 'languages', 'notes', 'is_active',
    ];

    /** Never leak financial data through JSON / Livewire snapshots. */
    protected $hidden = ['iban', 'social_security_number', 'hourly_rate_cents'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'iban' => 'encrypted',
            'social_security_number' => 'encrypted',
            'hourly_rate_cents' => 'integer',
            'languages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Sport, $this> */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class)->orderBy('position');
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** @param Builder<Instructor> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function teaches(Sport $sport): bool
    {
        return $this->sports->contains('id', $sport->id);
    }
}
