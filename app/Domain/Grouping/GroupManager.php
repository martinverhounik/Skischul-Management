<?php

namespace App\Domain\Grouping;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes to groups and assignments (US-22, US-24). The screen calls these;
 * every rule lives here or in AssignmentGuard.
 */
class GroupManager
{
    public function __construct(private Grouping $grouping) {}

    public function createGroup(CarbonImmutable $date, TimeslotKey $slot, int $sportId, int $ageGroupId): Group
    {
        $count = Group::query()->whereDate('date', $date->toDateString())
            ->where('timeslot_key', $slot)->where('sport_id', $sportId)->where('age_group_id', $ageGroupId)->count();

        return Group::query()->create([
            'date' => $date->toDateString(),
            'timeslot_key' => $slot,
            'sport_id' => $sportId,
            'age_group_id' => $ageGroupId,
            'name' => __('Group :n', ['n' => $count + 1]),
        ]);
    }

    /**
     * Puts every student without a group into groups: as many groups as
     * instructors are needed, students sorted by skill level so similar
     * levels end up together. Existing groups and members stay.
     */
    public function autoSplit(Cohort $cohort): void
    {
        DB::transaction(function () use ($cohort) {
            $groups = $cohort->groups->values();
            while ($groups->count() < max(1, $cohort->instructorsNeeded())) {
                $groups->push($this->createGroup($cohort->date, $cohort->timeslot, $cohort->sport->id, $cohort->ageGroup->id));
            }

            $size = (int) ceil($cohort->studentCount() / $groups->count());
            $filled = $groups->mapWithKeys(fn (Group $g) => [$g->id => $cohort->entriesOf($g)->count()])->all();

            foreach ($cohort->unassigned() as $entry) {
                // first group (in order) that still has room, else the emptiest one
                $target = collect($filled)->filter(fn ($n) => $n < $size)->keys()->first()
                    ?? collect($filled)->sort()->keys()->first();
                $this->addMember($target, $entry->lessonDay->id, $entry->student->id);
                $filled[$target]++;
            }
        });
    }

    /**
     * "Each morning starts as a copy of the day before": copies the groups
     * of the previous day with a lesson in this cohort, their meeting points,
     * instructors (when still free) and the students who have a lesson today.
     */
    public function copyPreviousDay(Cohort $cohort, School $school): int
    {
        $previous = Group::query()->with('members')
            ->whereDate('date', '<', $cohort->date->toDateString())
            ->whereDate('date', '>=', $cohort->date->subDays(7)->toDateString())
            ->where('timeslot_key', $cohort->timeslot)
            ->where('sport_id', $cohort->sport->id)
            ->where('age_group_id', $cohort->ageGroup->id)
            ->orderByDesc('date')->get();

        $lastDate = $previous->first()?->date?->toDateString();
        if (! $lastDate) {
            return 0;
        }

        $guard = AssignmentGuard::for($school);
        [$from, $to] = $guard->slotTimes($cohort->timeslot);
        $todayByStudent = $cohort->entries->keyBy(fn (GroupEntry $e) => $e->student->id);
        $copied = 0;

        DB::transaction(function () use ($previous, $lastDate, $cohort, $guard, $from, $to, $todayByStudent, &$copied) {
            foreach ($previous->filter(fn ($g) => $g->date->toDateString() === $lastDate) as $old) {
                $group = $cohort->groups->firstWhere('name', $old->name)
                    ?? Group::query()->create([
                        'date' => $cohort->date->toDateString(),
                        'timeslot_key' => $cohort->timeslot,
                        'sport_id' => $cohort->sport->id,
                        'age_group_id' => $cohort->ageGroup->id,
                        'name' => $old->name,
                        'meeting_point_id' => $old->meeting_point_id,
                    ]);

                if (! $group->instructor_id && $old->instructor_id) {
                    $instructor = Instructor::query()->with('sports')->find($old->instructor_id);
                    if ($instructor && $guard->check($instructor, $cohort->date, $from, $to, null, $group->id)['errors'] === []) {
                        $group->update(['instructor_id' => $instructor->id]);
                    }
                }

                foreach ($old->members as $member) {
                    $entry = $todayByStudent->get($member->student_id);
                    if ($entry && $entry->groupId === null) {
                        $this->addMember($group->id, $entry->lessonDay->id, $entry->student->id);
                        $entry->groupId = $group->id;
                        $copied++;
                    }
                }
            }
        });

        return $copied;
    }

    /**
     * Moves a student into a group, or out of all groups ($groupId null).
     * The group must be on the same day and time and in the same sport;
     * the age group may differ (skill level matters more).
     */
    public function moveStudent(int $lessonDayId, int $studentId, ?int $groupId): void
    {
        $lessonDay = LessonDay::query()->with('line.students')->findOrFail($lessonDayId);
        abort_unless($lessonDay->line->course_type === CourseType::Group, 422);
        abort_unless($lessonDay->line->students->contains('id', $studentId), 404);

        if ($groupId === null) {
            GroupMember::query()->where('lesson_day_id', $lessonDayId)->where('student_id', $studentId)->delete();

            return;
        }

        $group = Group::query()->findOrFail($groupId);
        if ($group->date->toDateString() !== $lessonDay->date->toDateString()
            || $group->timeslot_key !== $lessonDay->timeslot_key
            || $group->sport_id !== $lessonDay->line->sport_id) {
            throw ValidationException::withMessages(['group' => __('This group is on another day, time or sport.')]);
        }

        $this->addMember($group->id, $lessonDayId, $studentId);
    }

    public function deleteGroup(Group $group): void
    {
        $group->delete(); // members cascade and become "without group"
    }

    /**
     * US-24: assign or remove the instructor of a group. Overlaps and
     * inactive instructors are refused; a sport the instructor does not
     * teach needs $confirmed.
     *
     * @return list<string> warnings that still need confirmation (nothing saved)
     */
    public function assignGroup(Group $group, ?int $instructorId, School $school, bool $confirmed = false): array
    {
        if ($instructorId === null) {
            $group->update(['instructor_id' => null]);

            return [];
        }

        $instructor = Instructor::query()->with('sports')->findOrFail($instructorId);
        $guard = AssignmentGuard::for($school);
        [$from, $to] = $guard->slotTimes($group->timeslot_key);
        $result = $guard->check($instructor, CarbonImmutable::parse($group->date->toDateString()), $from, $to, $group->sport, exceptGroupId: $group->id);

        return $this->apply($result, $confirmed, fn () => $group->update(['instructor_id' => $instructor->id]));
    }

    /** US-24 for private lessons and guiding. */
    public function assignPrivate(LessonDay $lesson, ?int $instructorId, School $school, bool $confirmed = false): array
    {
        abort_unless($lesson->line->course_type->isHourly(), 422);

        if ($instructorId === null) {
            $lesson->update(['instructor_id' => null]);

            return [];
        }

        $instructor = Instructor::query()->with('sports')->findOrFail($instructorId);
        $result = AssignmentGuard::for($school)->check(
            $instructor, CarbonImmutable::parse($lesson->date->toDateString()),
            substr((string) $lesson->starts_at, 0, 5), substr((string) $lesson->ends_at, 0, 5),
            $lesson->line->sport, exceptLessonDayId: $lesson->id,
        );

        return $this->apply($result, $confirmed, fn () => $lesson->update(['instructor_id' => $instructor->id]));
    }

    private function apply(array $result, bool $confirmed, callable $save): array
    {
        if ($result['errors'] !== []) {
            throw ValidationException::withMessages(['instructor' => $result['errors']]);
        }

        if ($result['warnings'] !== [] && ! $confirmed) {
            return $result['warnings'];
        }

        $save();

        return [];
    }

    private function addMember(int $groupId, int $lessonDayId, int $studentId): void
    {
        GroupMember::query()->updateOrCreate(
            ['lesson_day_id' => $lessonDayId, 'student_id' => $studentId],
            ['group_id' => $groupId],
        );
    }
}
