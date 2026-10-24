<?php

namespace App\Livewire;

use App\Domain\Calendar\CalendarEntry;
use App\Domain\Calendar\CalendarFeed;
use App\Domain\Calendar\DayLayout;
use App\Domain\Calendar\SaveCalendarEvent;
use App\Domain\Calendar\SchoolCalendar;
use App\Domain\Dashboard\WeekNumbers;
use App\Domain\Grouping\GroupManager;
use App\Models\CalendarEvent;
use App\Models\Group;
use App\Models\Instructor;
use App\Models\LessonDay;
use App\Models\PriceTable;
use App\Models\School;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Start page (US-25, US-26): this week's numbers, a quick link to the
 * booking screen, and the planning calendar.
 *
 * Course entries come live from Grouping: changing an instructor here
 * changes the same assignment in Grouping (same GroupManager, same rules).
 * Standalone entries (day off, sick leave, meeting …) are stored as
 * CalendarEvent and block the instructor for courses.
 */
class Dashboard extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public string $view = 'week';

    #[Url]
    public string $course = '';

    #[Url]
    public string $instructor = '';

    /** The course entry open in the side panel: kind, id, date. */
    public ?array $selected = null;

    /** Standalone entry form: null = closed, 0 = new, id = editing. */
    public ?int $eventId = null;

    public array $event = [];

    /** @var array{type: string, id: int, instructor: int, warnings: list<string>}|null */
    public ?array $pending = null;

    public ?string $status = null;

    /** Bumped when an assignment is refused or cancelled, to redraw the select. */
    public int $redraw = 0;

    public function mount(): void
    {
        if (! $this->parse($this->date)) {
            $this->date = $this->school()->today()->toDateString();
        }
        $this->view = in_array($this->view, ['day', 'week', 'month'], true) ? $this->view : 'week';
    }

    private function school(): School
    {
        return app(CurrentSchool::class)->get()->loadMissing('timeslots');
    }

    private function day(): CarbonImmutable
    {
        return $this->parse($this->date) ?? CarbonImmutable::today();
    }

    private function startOfWeek(CarbonImmutable $day): CarbonImmutable
    {
        return $day->subDays(($day->dayOfWeek - $this->school()->week_starts_on + 7) % 7);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day shown */
    private function range(): array
    {
        $day = $this->day();

        return match ($this->view) {
            'day' => [$day, $day],
            'month' => [$this->startOfWeek($day->startOfMonth()), $this->startOfWeek($day->endOfMonth()->startOfDay())->addDays(6)],
            default => [$this->startOfWeek($day), $this->startOfWeek($day)->addDays(6)],
        };
    }

    // --- Navigation ---

    public function previous(): void
    {
        $this->move(-1);
    }

    public function next(): void
    {
        $this->move(1);
    }

    private function move(int $direction): void
    {
        $day = $this->day();
        $target = match ($this->view) {
            'day' => $day->addDays($direction),
            'month' => $day->startOfMonth()->addMonths($direction),
            default => $day->addDays(7 * $direction),
        };
        $this->date = $target->toDateString();
        $this->closePanel();
    }

    public function today(): void
    {
        $this->date = $this->school()->today()->toDateString();
        $this->closePanel();
    }

    public function showDay(string $date): void
    {
        if ($this->parse($date)) {
            $this->date = $date;
            $this->view = 'day';
            $this->closePanel();
        }
    }

    public function resetFilters(): void
    {
        $this->course = '';
        $this->instructor = '';
    }

    // --- Side panel: course entries (synced with Grouping, US-24) ---

    public function open(string $kind, ?int $id, string $date): void
    {
        $this->closePanel();
        if ($kind === CalendarEntry::EVENT && $id) {
            $this->editEvent($id);

            return;
        }
        $this->selected = ['kind' => $kind, 'id' => $id, 'date' => $date];
    }

    public function closePanel(): void
    {
        $this->reset('selected', 'eventId', 'event', 'pending', 'status');
        $this->resetErrorBag();
    }

    public function assign(string $instructorId, bool $confirmed = false): void
    {
        if (! $this->selected || ! $this->selected['id']) {
            return;
        }
        $this->resetErrorBag('instructor');
        $this->pending = null;
        $id = $instructorId === '' ? null : (int) $instructorId;
        $manager = app(GroupManager::class);
        $kind = $this->selected['kind'];

        try {
            $warnings = $kind === CalendarEntry::GROUP
                ? $manager->assignGroup(Group::query()->with('sport')->findOrFail($this->selected['id']), $id, $this->school(), $confirmed)
                : $manager->assignPrivate(LessonDay::query()->with('line.sport')->findOrFail($this->selected['id']), $id, $this->school(), $confirmed);
        } catch (ValidationException $e) {
            $this->addError('instructor', implode(' ', $e->errors()['instructor'] ?? [$e->getMessage()]));
            $this->redraw++;

            return;
        }

        if ($warnings !== []) {
            $this->pending = ['type' => $kind, 'id' => $this->selected['id'], 'instructor' => $id, 'warnings' => $warnings];

            return;
        }
        $this->status = $id ? __('Instructor assigned.') : __('Assignment removed.');
    }

    public function cancelPending(): void
    {
        $this->pending = null;
        $this->redraw++;
    }

    public function confirmPending(): void
    {
        if ($this->pending) {
            $instructor = (string) $this->pending['instructor'];
            $this->assign($instructor, true);
        }
    }

    // --- Side panel: standalone entries (US-26) ---

    public function newEvent(?string $date = null): void
    {
        $this->closePanel();
        $date = $this->parse($date) ? $date : $this->day()->toDateString();
        $this->eventId = 0;
        $this->event = [
            'title' => '', 'starts_on' => $date, 'ends_on' => $date, 'all_day' => true,
            'starts_at' => '09:00', 'ends_at' => '10:00',
            'instructor_id' => $this->instructor, 'notes' => '',
        ];
    }

    private function editEvent(int $id): void
    {
        $event = CalendarEvent::query()->findOrFail($id);
        $this->eventId = $event->id;
        $this->event = [
            'title' => $event->title,
            'starts_on' => $event->starts_on->toDateString(),
            'ends_on' => $event->ends_on->toDateString(),
            'all_day' => $event->isAllDay(),
            'starts_at' => $event->starts_at ? substr($event->starts_at, 0, 5) : '09:00',
            'ends_at' => $event->ends_at ? substr($event->ends_at, 0, 5) : '10:00',
            'instructor_id' => (string) ($event->instructor_id ?? ''),
            'notes' => (string) $event->notes,
        ];
    }

    public function saveEvent(): void
    {
        $this->resetErrorBag();
        $existing = $this->eventId ? CalendarEvent::query()->findOrFail($this->eventId) : null;

        try {
            app(SaveCalendarEvent::class)->save($this->school(), $this->event, $existing, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('event.'.$field, $messages[0]);
            }

            return;
        }

        $this->closePanel();
        $this->status = $existing ? __('Entry saved.') : __('Entry added.');
    }

    public function deleteEvent(): void
    {
        if ($this->eventId) {
            CalendarEvent::query()->findOrFail($this->eventId)->delete();
        }
        $this->closePanel();
        $this->status = __('Entry deleted.');
    }

    private function parse(?string $value): ?CarbonImmutable
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }

    private function filterId(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    /** The selected course entry, re-read so it always matches Grouping. */
    private function selectedEntry(Collection $days): ?CalendarEntry
    {
        if (! $this->selected) {
            return null;
        }
        $date = $this->selected['date'];
        $list = $days->get($date) ?? CalendarFeed::for($this->school())->between(CarbonImmutable::parse($date), CarbonImmutable::parse($date))->first();

        return $list->first(fn (CalendarEntry $e) => $e->kind === $this->selected['kind']
            && ($e->id === $this->selected['id'] || ($e->id === null && $this->selected['id'] === null)));
    }

    public function render()
    {
        $school = $this->school();
        [$from, $to] = $this->range();
        $days = CalendarFeed::for($school)->between($from, $to, $this->filterId($this->course), $this->filterId($this->instructor));
        $calendar = SchoolCalendar::for($school);

        // Hours shown in the day and week grid: at least 8–17, wider if needed.
        $timed = $days->flatten(1)->reject(fn (CalendarEntry $e) => $e->allDay);
        $earliest = $timed->min(fn ($e) => $e->startMinutes());
        $startHour = $earliest === null ? 8 : min(8, intdiv((int) $earliest, 60));
        $endHour = (int) min(24, max(17, (int) ceil(($timed->max(fn ($e) => $e->endMinutes()) ?? 0) / 60)));

        $instructors = Instructor::query()->with('sports')->orderBy('first_name')->get();
        $selectedEntry = $this->selectedEntry($days);

        return view('livewire.dashboard', [
            'school' => $school,
            'numbers' => new WeekNumbers($school),
            'day' => $this->day(),
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'calendar' => $calendar,
            'today' => $school->today()->toDateString(),
            'startHour' => $startHour,
            'endHour' => $endHour,
            'layouts' => $this->view === 'month' ? collect() : $days->map(fn ($list) => DayLayout::lanes($list->reject(fn ($e) => $e->allDay))),
            'courses' => PriceTable::query()->with('sports')->orderBy('id')->get()->sortBy(fn ($t) => $t->displayName()),
            'instructors' => $instructors,
            'selectedEntry' => $selectedEntry,
        ])->title(__('Dashboard'));
    }
}
