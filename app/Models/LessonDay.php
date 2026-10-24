<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One actual lesson: a day of a group course, or a private lesson.
 * Grouping (step 7) and the calendar (step 8) work from these.
 *
 * @property int $id
 * @property int $booking_line_id
 * @property Carbon $date
 * @property TimeslotKey $timeslot_key
 * @property string|null $starts_at private lessons only
 * @property string|null $ends_at private lessons only
 * @property int|null $instructor_id private lessons only
 * @property int|null $meeting_point_id private lessons only
 */
class LessonDay extends Model
{
    use BelongsToSchool;

    // Only created by PlaceOrder, which sets school_id explicitly.
    protected $fillable = ['school_id', 'date', 'timeslot_key', 'starts_at', 'ends_at', 'instructor_id', 'meeting_point_id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'timeslot_key' => TimeslotKey::class];
    }

    /** Private lessons only: the assigned instructor (US-24). */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function meetingPoint(): BelongsTo
    {
        return $this->belongsTo(MeetingPoint::class);
    }

    /** @return HasMany<GroupMember, $this> */
    public function groupMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /** @return BelongsTo<BookingLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(BookingLine::class, 'booking_line_id');
    }
}
