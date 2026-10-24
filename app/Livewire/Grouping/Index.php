<?php

namespace App\Livewire\Grouping;

use App\Domain\Grouping\Cohort;
use App\Domain\Grouping\Grouping;
use App\Domain\Grouping\GroupManager;
use App\Models\Group;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\MeetingPoint;
use App\Models\School;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Grouping (US-22 to US-24): who skis when, how many instructors are
 * needed, and who teaches which group or private lesson. Day and week view
 * with a time navigation bar (US-23).
 */
class Index extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public string $view = 'day';

    public ?string $status = null;

    /**
     * An assignment that needs confirmation because of a warning
     * (e.g. the instructor does not teach this sport).
     *
     * @var array{type: string, id: int, instructor: int, warnings: list<string>}|null
     */
    public ?array $pending = null;

    /**
     * Bumped when an assignment is refused or cancelled, so the select
     * boxes are redrawn and show the stored instructor again.
     */
    public int $redraw = 0;

    public function mount(): void
    {
        if (! $this->parse($this->date)) {
            $school = $this->school();
            $today = CarbonImmutable::parse($school->today()->toDateString());
            $start = $school->season_starts_on ? CarbonImmutable::parse($school->season_starts_on->toDateString()) : null;
            $this->date = ($start && $today->lt($start) ? $start : $today)->toDateString();
        }
        $this->view = in_array($this->view, ['day', 'week'], true) ? $this->view : 'day';
    }

    private function school(): School
    {
        return app(CurrentSchool::class)->get()->loadMissing('timeslots');
    }

    private function day(): CarbonImmutable
    {
        return $this->parse($this->date) ?? CarbonImmutable::today();
    }

    public function weekStart(): CarbonImmutable
    {
        $day = $this->day();
        $offset = ($day->dayOfWeek - $this->school()->week_starts_on + 7) % 7;

        return $day->subDays($offset);
    }

    // --- Time navigation (US-23) ---

    public function previous(): void
    {
        $this->date = $this->day()->subDays($this->view === 'week' ? 7 : 1)->toDateString();
        $this->reset('pending', 'status');
    }

    public function next(): void
    {
        $this->date = $this->day()->addDays($this->view === 'week' ? 7 : 1)->toDateString();
        $this->reset('pending', 'status');
    }

    public function today(): void
    {
        $this->date = $this->school()->today()->toDateString();
        $this->reset('pending', 'status');
    }

    public function showDay(string $date): void
    {
        if ($this->parse($date)) {
            $this->date = $date;
            $this->view = 'day';
            $this->reset('pending', 'status');
        }
    }

    // --- Groups (US-22) ---

    private function cohort(string $key): Cohort
    {
        $cohort = app(Grouping::class)->cohorts($this->day())->get($key);
        abort_unless($cohort, 404);

        return $cohort;
    }

    public function addGroup(string $key): void
    {
        $c = $this->cohort($key);
        app(GroupManager::class)->createGroup($c->date, $c->timeslot, $c->sport->id, $c->ageGroup->id);
    }

    public function autoSplit(string $key): void
    {
        app(GroupManager::class)->autoSplit($this->cohort($key));
        $this->status = __('All students without a group have been placed.');
    }

    public function copyPreviousDay(string $key): void
    {
        $n = app(GroupManager::class)->copyPreviousDay($this->cohort($key), $this->school());
        $this->status = $n > 0
            ? trans_choice(':count student was placed like on the previous day.|:count students were placed like on the previous day.', $n)
            : __('There are no groups from the previous days to copy.');
    }

    public function moveStudent(int $lessonDayId, int $studentId, string $groupId): void
    {
        try {
            app(GroupManager::class)->moveStudent($lessonDayId, $studentId, $groupId === '' ? null : (int) $groupId);
        } catch (ValidationException $e) {
            $this->addError('group', $e->getMessage());
        }
    }

    public function renameGroup(int $groupId, string $name): void
    {
        $name = trim($name);
        if ($name !== '' && mb_strlen($name) <= 100) {
            Group::query()->findOrFail($groupId)->update(['name' => $name]);
        }
    }

    public function deleteGroup(int $groupId): void
    {
        app(GroupManager::class)->deleteGroup(Group::query()->findOrFail($groupId));
    }

    public function setGroupMeetingPoint(int $groupId, string $meetingPointId): void
    {
        Group::query()->findOrFail($groupId)->update(['meeting_point_id' => $this->meetingPointId($meetingPointId)]);
    }

    // --- Instructors (US-24) ---

    public function assignGroup(int $groupId, string $instructorId, bool $confirmed = false): void
    {
        $group = Group::query()->with('sport')->findOrFail($groupId);
        $this->assign('group', $group->id, $instructorId, $confirmed,
            fn ($id, $ok) => app(GroupManager::class)->assignGroup($group, $id, $this->school(), $ok));
    }

    public function assignPrivate(int $lessonDayId, string $instructorId, bool $confirmed = false): void
    {
        $lesson = LessonDay::query()->with('line.sport')->findOrFail($lessonDayId);
        $this->assign('private', $lesson->id, $instructorId, $confirmed,
            fn ($id, $ok) => app(GroupManager::class)->assignPrivate($lesson, $id, $this->school(), $ok));
    }

    public function setPrivateMeetingPoint(int $lessonDayId, string $meetingPointId): void
    {
        LessonDay::query()->findOrFail($lessonDayId)->update(['meeting_point_id' => $this->meetingPointId($meetingPointId)]);
    }

    public function confirmPending(): void
    {
        if (! $this->pending) {
            return;
        }
        $p = $this->pending;
        $this->pending = null;
        $p['type'] === 'group'
            ? $this->assignGroup($p['id'], (string) $p['instructor'], true)
            : $this->assignPrivate($p['id'], (string) $p['instructor'], true);
    }

    public function cancelPending(): void
    {
        $this->pending = null;
        $this->redraw++;
    }

    private function assign(string $type, int $id, string $instructorId, bool $confirmed, callable $action): void
    {
        $this->resetErrorBag('instructor');
        $this->pending = null;
        $instructor = $instructorId === '' ? null : (int) $instructorId;

        try {
            $warnings = $action($instructor, $confirmed);
        } catch (ValidationException $e) {
            $this->addError('instructor', implode(' ', $e->errors()['instructor'] ?? [$e->getMessage()]));
            $this->redraw++;

            return;
        }

        if ($warnings !== []) {
            $this->pending = ['type' => $type, 'id' => $id, 'instructor' => $instructor, 'warnings' => $warnings];
        }
    }

    private function meetingPointId(string $value): ?int
    {
        return $value === '' ? null : MeetingPoint::query()->findOrFail((int) $value)->id;
    }

    private function parse(?string $value): ?CarbonImmutable
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }

    public function render()
    {
        $grouping = app(Grouping::class);
        $school = $this->school();
        $day = $this->day();
        $instructors = Instructor::query()->with('sports')->orderBy('first_name')->get();

        $data = [
            'school' => $school,
            'day' => $day,
            'weekStart' => $this->weekStart(),
            'instructors' => $instructors,
            'activeInstructors' => $instructors->where('is_active', true)->values(),
            'meetingPoints' => MeetingPoint::query()->orderBy('name')->get(),
        ];

        if ($this->view === 'week') {
            $week = $grouping->week($this->weekStart());
            $rows = $week->flatMap(fn ($d) => $d['cohorts'])->unique(fn (Cohort $c) => $c->key())
                ->sortBy(fn (Cohort $c) => sprintf('%05d-%03d-%s', $c->sport->position, $c->ageGroup->min_age, $c->timeslot->value))
                ->values();
            $data += ['week' => $week, 'rows' => $rows];
        } else {
            $cohorts = $grouping->cohorts($day);
            $data += [
                'cohorts' => $cohorts,
                'bySport' => $cohorts->groupBy(fn (Cohort $c) => $c->sport->id),
                'private' => $grouping->privateLessons($day),
                // Groups a student can be moved to: same time and sport.
                'groupChoices' => $cohorts->flatMap(fn (Cohort $c) => $c->groups)
                    ->groupBy(fn (Group $g) => $g->timeslot_key->value.'-'.$g->sport_id),
            ];
        }

        return view('livewire.grouping.index', $data)->title(__('Grouping'));
    }
}
