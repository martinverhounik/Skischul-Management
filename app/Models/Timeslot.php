<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Start and end time of one of the fixed timeslots (US-06). Courses and
 * price tables refer to the slot, not to copied times, so editing a slot
 * changes it everywhere (US-30).
 *
 * @property int $id
 * @property TimeslotKey $key
 * @property string $starts_at H:i:s
 * @property string $ends_at H:i:s
 */
class Timeslot extends Model
{
    use BelongsToSchool;

    protected $fillable = ['key', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['key' => TimeslotKey::class];
    }

    public function startsAtLabel(): string
    {
        return substr($this->starts_at, 0, 5);
    }

    public function endsAtLabel(): string
    {
        return substr($this->ends_at, 0, 5);
    }

    /** Length in minutes. */
    public function durationMinutes(): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $this->starts_at));
        [$eh, $em] = array_map('intval', explode(':', $this->ends_at));

        return ($eh * 60 + $em) - ($sh * 60 + $sm);
    }
}
