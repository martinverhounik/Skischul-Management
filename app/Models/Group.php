<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Students who ski together on one day and time, with one instructor and a
 * meeting point (US-22, US-24). Groups exist per day, so they can be merged
 * or split during the week.
 *
 * @property int $id
 * @property Carbon $date
 * @property TimeslotKey $timeslot_key
 * @property int $sport_id
 * @property int $age_group_id
 * @property string $name
 * @property int|null $instructor_id
 * @property int|null $meeting_point_id
 * @property string|null $notes
 */
class Group extends Model
{
    use BelongsToSchool;

    protected $fillable = ['date', 'timeslot_key', 'sport_id', 'age_group_id', 'name', 'instructor_id', 'meeting_point_id', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'timeslot_key' => TimeslotKey::class];
    }

    /** @return HasMany<GroupMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /** @return BelongsTo<Instructor, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /** @return BelongsTo<MeetingPoint, $this> */
    public function meetingPoint(): BelongsTo
    {
        return $this->belongsTo(MeetingPoint::class);
    }

    /** @return BelongsTo<Sport, $this> */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /** @return BelongsTo<AgeGroup, $this> */
    public function ageGroup(): BelongsTo
    {
        return $this->belongsTo(AgeGroup::class);
    }
}
