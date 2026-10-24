<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A calendar-only entry (US-26): day off, sick leave, meeting, blocked slot.
 * Without times it lasts the whole day. Linked to an instructor, it blocks
 * them for course assignments (US-30).
 *
 * @property int $id
 * @property string $title
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property int|null $instructor_id
 * @property string|null $notes
 */
class CalendarEvent extends Model
{
    use BelongsToSchool;

    protected $fillable = ['title', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'instructor_id', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /** @return BelongsTo<Instructor, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function isAllDay(): bool
    {
        return $this->starts_at === null;
    }

    /** Start time as H:i (00:00 for all-day entries). */
    public function fromLabel(): string
    {
        return $this->starts_at ? substr($this->starts_at, 0, 5) : '00:00';
    }

    /** End time as H:i (24:00 for all-day entries). */
    public function toLabel(): string
    {
        return $this->ends_at ? substr($this->ends_at, 0, 5) : '24:00';
    }

    /** Entries touching the days from … to (inclusive). */
    public function scopeBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString());
    }
}
