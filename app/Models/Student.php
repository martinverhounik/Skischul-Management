<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Anyone who has used the school's services (US-17 to US-19).
 *
 * Archived students are hidden from default lists but stay in their
 * historical bookings.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $date_of_birth
 * @property int $skill_level_id
 * @property string $primary_phone
 * @property string $primary_phone_owner
 * @property string|null $secondary_phone
 * @property string|null $secondary_phone_owner
 * @property string|null $preferred_language
 * @property string|null $accommodation
 * @property string|null $medical_notes
 * @property string|null $notes
 * @property Carbon|null $archived_at
 */
class Student extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'date_of_birth', 'skill_level_id',
        'primary_phone', 'primary_phone_owner', 'secondary_phone', 'secondary_phone_owner',
        'preferred_language', 'accommodation', 'medical_notes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SkillLevel, $this> */
    public function skillLevel(): BelongsTo
    {
        return $this->belongsTo(SkillLevel::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1));
    }

    /** Age in whole years on a given day (bookings use the first course day). */
    public function ageOn(CarbonInterface $day): int
    {
        $years = $this->date_of_birth->copy()->startOfDay()->diffInYears(Carbon::parse($day->toDateString()));

        return max(0, (int) floor($years));
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** @param Builder<Student> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Search by name, phone number or accommodation.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        foreach (preg_split('/\s+/', $term) as $word) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';
            $digits = preg_replace('/\D/', '', $word);

            $query->where(function (Builder $q) use ($like, $digits) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('accommodation', 'like', $like);

                if (strlen($digits) >= 3) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(primary_phone, ' ', ''), '/', ''), '-', '') like ?", ["%{$digits}%"])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(secondary_phone, ' ', ''), '/', ''), '-', '') like ?", ["%{$digits}%"]);
                }
            });
        }
    }
}
