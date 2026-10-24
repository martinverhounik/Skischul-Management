<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student in a group on one lesson day.
 *
 * @property int $group_id
 * @property int $lesson_day_id
 * @property int $student_id
 */
class GroupMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['group_id', 'lesson_day_id', 'student_id'];

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<LessonDay, $this> */
    public function lessonDay(): BelongsTo
    {
        return $this->belongsTo(LessonDay::class);
    }
}
