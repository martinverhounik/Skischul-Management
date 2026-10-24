<?php

namespace App\Domain\Calendar;

use App\Domain\Grouping\AssignmentGuard;
use App\Models\CalendarEvent;
use App\Models\Instructor;
use App\Models\School;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a standalone calendar entry (US-26). An entry for an
 * instructor may not overlap a course they already teach (US-30): unassign
 * them in Grouping first.
 */
class SaveCalendarEvent
{
    /** Longest entry, e.g. a holiday: keeps typos like 2072 harmless. */
    public const MAX_DAYS = 62;

    /**
     * @param  array{title?: mixed, starts_on?: mixed, ends_on?: mixed, all_day?: mixed, starts_at?: mixed, ends_at?: mixed, instructor_id?: mixed, notes?: mixed}  $input
     */
    public function save(School $school, array $input, ?CalendarEvent $event = null, ?User $user = null): CalendarEvent
    {
        $input['all_day'] = filter_var($input['all_day'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $input['ends_on'] = ($input['ends_on'] ?? '') ?: ($input['starts_on'] ?? null);
        $input['instructor_id'] = ($input['instructor_id'] ?? '') ?: null;

        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'all_day' => ['boolean'],
            'starts_at' => ['exclude_if:all_day,true', 'required', 'date_format:H:i'],
            'ends_at' => ['exclude_if:all_day,true', 'required', 'date_format:H:i', 'after:starts_at'],
            'instructor_id' => ['nullable', 'integer', Rule::exists('instructors', 'id')->where('school_id', $school->id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'title' => __('Title'), 'starts_on' => __('From'), 'ends_on' => __('Until'),
            'starts_at' => __('Start'), 'ends_at' => __('End'), 'instructor_id' => __('Instructor'),
        ])->validate();

        $first = CarbonImmutable::createFromFormat('!Y-m-d', $data['starts_on']);
        $last = CarbonImmutable::createFromFormat('!Y-m-d', $data['ends_on']);
        if ($first->diffInDays($last) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['ends_on' => __('An entry can last at most :n days.', ['n' => self::MAX_DAYS])]);
        }

        $from = $data['all_day'] ? '00:00' : $data['starts_at'];
        $to = $data['all_day'] ? '24:00' : $data['ends_at'];

        if ($data['instructor_id']) {
            $this->refuseOverlaps($school, Instructor::query()->findOrFail($data['instructor_id']), $first, $last, $from, $to);
        }

        $event ??= new CalendarEvent(['created_by' => $user?->id]);
        $event->fill([
            'title' => trim($data['title']),
            'starts_on' => $first->toDateString(),
            'ends_on' => $last->toDateString(),
            'starts_at' => $data['all_day'] ? null : $data['starts_at'],
            'ends_at' => $data['all_day'] ? null : $data['ends_at'],
            'instructor_id' => $data['instructor_id'],
            'notes' => ($data['notes'] ?? '') ?: null,
        ])->save();

        return $event;
    }

    private function refuseOverlaps(School $school, Instructor $instructor, CarbonImmutable $first, CarbonImmutable $last, string $from, string $to): void
    {
        $guard = AssignmentGuard::for($school);
        $messages = [];
        for ($d = $first; $d->lte($last); $d = $d->addDay()) {
            foreach ($guard->conflicts($instructor, $d, $from, $to) as $busy) {
                $messages[] = __(':name teaches on :date :from–:to (:what).', [
                    'name' => $instructor->fullName(), 'date' => $d->isoFormat('dd D.M.'),
                    'from' => $busy['from'], 'to' => $busy['to'], 'what' => $busy['what'],
                ]);
            }
        }

        if ($messages !== []) {
            $messages[] = __('Remove the assignment in Grouping first.');
            throw ValidationException::withMessages(['instructor_id' => implode(' ', $messages)]);
        }
    }
}
