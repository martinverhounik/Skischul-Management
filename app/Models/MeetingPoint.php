<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a course starts, with an optional map link (US-05).
 *
 * @property int $id
 * @property string $name
 * @property string|null $map_url
 */
class MeetingPoint extends Model
{
    use BelongsToSchool, GuardsDeletion;

    protected $fillable = ['name', 'map_url'];

    protected function usages(): array
    {
        return [
            __('Groups') => fn () => Group::query()->where('meeting_point_id', $this->id)->count(),
            __('Private lessons') => fn () => LessonDay::query()->where('meeting_point_id', $this->id)->count(),
        ];
    }
}
