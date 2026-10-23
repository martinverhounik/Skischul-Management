<?php

namespace App\Livewire\Booking;

use App\Domain\Booking\BookingRules;
use App\Domain\Booking\Cart;
use App\Domain\Booking\Quote;
use App\Domain\Calendar\SchoolCalendar;
use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Livewire\Forms\StudentForm;
use App\Models\AddOn;
use App\Models\AgeGroup;
use App\Models\PriceTable;
use App\Models\School;
use App\Models\SkillLevel;
use App\Models\Sport;
use App\Models\Student;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The office's booking screen ("course shop", back office only):
 * find courses (US-09), configure one with participants (US-10, US-11),
 * and collect them in the cart with extras (US-12).
 */
class Shop extends Component
{
    // --- Filters (US-09) ---
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $sport = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $age = '';

    #[Url(except: '')]
    public string $level = '';

    // --- The course being configured ---
    public ?int $configuring = null;

    /** Cart key when an existing cart item is being changed. */
    public ?string $editingKey = null;

    public array $item = [];

    public string $studentSearch = '';

    public bool $showNewStudent = false;

    public StudentForm $newStudent;

    public ?string $status = null;

    private function school(): School
    {
        return app(CurrentSchool::class)->get()->loadMissing('timeslots');
    }

    private function cart(): Cart
    {
        return app(Cart::class);
    }

    // ------------------------------------------------------------------
    // Course list
    // ------------------------------------------------------------------

    /** The date window used for filters and for the date lists. */
    public function window(): array
    {
        $school = $this->school();
        $today = $school->today()->toImmutable();
        $seasonStart = $school->season_starts_on?->toImmutable();
        $seasonEnd = $school->season_ends_on?->toImmutable();

        $from = $this->parseDate($this->from) ?? ($seasonStart && $seasonStart->gt($today) ? $seasonStart : $today);
        $to = $this->parseDate($this->to) ?? $from->addDays(27);
        if ($seasonEnd && $to->gt($seasonEnd)) {
            $to = $seasonEnd;
        }

        return [$from, $to];
    }

    /** @return Collection<int, PriceTable> */
    #[Computed]
    public function courses(): Collection
    {
        [$from, $to] = $this->window();

        return PriceTable::query()
            ->with('sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices')
            ->where('is_active', true)
            ->when($this->type !== '', fn ($q) => $q->where('course_type', $this->type))
            ->when($this->sport !== '', fn ($q) => $q->whereHas('sports', fn ($s) => $s->whereKey((int) $this->sport)))
            ->when($this->age !== '', fn ($q) => $q->whereHas('ageGroups', fn ($s) => $s->whereKey((int) $this->age)))
            ->when($this->level !== '', fn ($q) => $q->whereHas('skillLevels', fn ($s) => $s->whereKey((int) $this->level)))
            ->orderBy('course_type')->orderBy('id')
            ->get()
            ->filter(fn (PriceTable $t) => $this->startDates($t, $from, $to)->isNotEmpty())
            ->values();
    }

    /**
     * Days a course can start on inside the window: in season, not closed,
     * and (group courses) one of the allowed start days.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function startDates(PriceTable $table, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if ($to->lt($from)) {
            return collect();
        }

        $calendar = SchoolCalendar::for($this->school());

        return collect(CarbonPeriod::create($from, $to))
            ->map(fn ($d) => CarbonImmutable::parse($d->toDateString()))
            ->filter(fn (CarbonImmutable $d) => $calendar->canStartOn($d)
                && ($table->course_type !== CourseType::Group || in_array($d->dayOfWeek, $table->start_weekdays ?? [], true)))
            ->values();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['from', 'to', 'sport', 'type', 'age', 'level'], true)) {
            unset($this->courses);
        }
    }

    public function resetFilters(): void
    {
        $this->reset('from', 'to', 'sport', 'type', 'age', 'level');
        unset($this->courses);
    }

    // ------------------------------------------------------------------
    // Configuring a course
    // ------------------------------------------------------------------

    public function configure(int $tableId): void
    {
        $table = PriceTable::query()->with('sports', 'timeOptions')->findOrFail($tableId);
        [$from, $to] = $this->window();
        $first = $this->startDates($table, $from, $to)->first();

        $this->editingKey = null;
        $this->configuring = $table->id;
        $this->item = [
            'price_table_id' => $table->id,
            'sport_id' => $this->sport !== '' && $table->sports->contains('id', (int) $this->sport)
                ? (int) $this->sport
                : $table->sports->first()?->id,
            'timeslot_key' => $table->activeTimeslotKeys()[0]->value ?? '',
            'start_date' => $first?->toDateString() ?? '',
            'days' => $table->course_type === CourseType::Group ? $table->max_days : 0,
            'start_time' => $table->course_type->isHourly() ? ($this->school()->timeslot(TimeslotKey::Morning)?->startsAtLabel() ?? '10:00') : '',
            // Start with the shortest bookable lesson.
            'hours' => $table->course_type->isHourly() ? max(1, (int) ($table->timeOptions->min('min_hours') ?? 1)) : 0,
            'students' => [],
        ];
        $this->resetStudentPicker();
    }

    public function editCartItem(string $key): void
    {
        $item = $this->cart()->item($this->school(), $key);
        if (! $item) {
            return;
        }

        $this->editingKey = $key;
        $this->configuring = $item['price_table_id'];
        $this->item = $item;
        $this->resetStudentPicker();
    }

    /** Plan question 3: a second private lesson with the same participants. */
    public function addAnotherLesson(string $key): void
    {
        $item = $this->cart()->item($this->school(), $key);
        if (! $item) {
            return;
        }

        $date = $this->parseDate($item['start_date']);
        $this->editingKey = null;
        $this->configuring = $item['price_table_id'];
        $this->item = array_merge($item, ['start_date' => $date?->addDay()->toDateString() ?? '']);
        $this->resetStudentPicker();
    }

    public function closeConfigurator(): void
    {
        $this->reset('configuring', 'editingKey', 'item');
        $this->resetStudentPicker();
    }

    #[Computed]
    public function quote(): ?Quote
    {
        if ($this->configuring === null) {
            return null;
        }

        return app(BookingRules::class)->check($this->school(), $this->item);
    }

    public function updatedItem(): void
    {
        unset($this->quote);
    }

    public function addToCart(): void
    {
        $quote = $this->quote;
        if (! $quote || ! $quote->isValid()) {
            return;
        }

        if ($this->editingKey) {
            $this->cart()->update($this->school(), $this->editingKey, $this->item);
            $this->status = __('The course in the cart has been updated.');
        } else {
            $this->cart()->add($this->school(), $this->item);
            $this->status = __(':course has been added to the cart.', ['course' => $quote->priceTable->displayName()]);
        }

        $this->closeConfigurator();
    }

    // ------------------------------------------------------------------
    // Participants
    // ------------------------------------------------------------------

    /** @return Collection<int, Student> */
    #[Computed]
    public function studentResults(): Collection
    {
        if (mb_strlen(trim($this->studentSearch)) < 2) {
            return collect();
        }

        $chosen = collect($this->item['students'] ?? [])->pluck('id')->all();

        return Student::query()->with('skillLevel')->active()->search($this->studentSearch)
            ->whereNotIn('id', $chosen)->orderBy('last_name')->limit(8)->get();
    }

    public function addStudent(int $id): void
    {
        $student = Student::query()->active()->findOrFail($id);
        $students = collect($this->item['students'] ?? []);
        if (! $students->contains('id', $student->id)) {
            $students->push(['id' => $student->id, 'age_override' => false]);
        }
        $this->item['students'] = $students->values()->all();
        $this->studentSearch = '';
        unset($this->quote, $this->studentResults);
    }

    public function removeStudent(int $id): void
    {
        $this->item['students'] = collect($this->item['students'] ?? [])->reject(fn ($s) => (int) $s['id'] === $id)->values()->all();
        unset($this->quote);
    }

    public function allowAge(int $id): void
    {
        $this->item['students'] = collect($this->item['students'] ?? [])
            ->map(fn ($s) => (int) $s['id'] === $id ? ['id' => $id, 'age_override' => true] : $s)->all();
        unset($this->quote);
    }

    /** US-10 / US-11: add a new student via the student form. */
    public function createStudent(): void
    {
        $student = $this->newStudent->save();
        $this->newStudent->reset();
        $this->showNewStudent = false;
        $this->addStudent($student->id);
    }

    private function resetStudentPicker(): void
    {
        $this->studentSearch = '';
        $this->showNewStudent = false;
        $this->newStudent->reset();
        $this->resetErrorBag();
        unset($this->quote, $this->studentResults);
    }

    // ------------------------------------------------------------------
    // Cart (US-12)
    // ------------------------------------------------------------------

    public function removeFromCart(string $key): void
    {
        $this->cart()->remove($this->school(), $key);
        if ($this->editingKey === $key) {
            $this->closeConfigurator();
        }
        $this->status = __('Removed from the cart.');
    }

    public function setAddOn(int $addOnId, int $quantity): void
    {
        AddOn::query()->where('is_active', true)->findOrFail($addOnId);
        $this->cart()->setAddOn($this->school(), $addOnId, max(0, $quantity));
    }

    public function render()
    {
        $school = $this->school();
        [$from, $to] = $this->window();
        $table = $this->configuring ? $this->courses->firstWhere('id', $this->configuring)
            ?? PriceTable::query()->with('sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices')->find($this->configuring) : null;

        $chosenStudents = Student::query()->with('skillLevel')
            ->whereKey(collect($this->item['students'] ?? [])->pluck('id'))->get()->keyBy('id');

        return view('livewire.booking.shop', [
            'school' => $school,
            // Named differently from the $from / $to filter properties.
            'windowFrom' => $from,
            'windowTo' => $to,
            'table' => $table,
            'dateOptions' => $table ? $this->startDates($table, $from, $to) : collect(),
            'chosenStudents' => $chosenStudents,
            'cartQuotes' => $this->cart()->quotes($school),
            'cartAddOns' => $this->cart()->addOns($school),
            'subtotal' => $this->cart()->subtotal($school),
            'addOns' => AddOn::query()->where('is_active', true)->orderBy('name')->get(),
            'sports' => Sport::query()->orderBy('position')->get(),
            'ageGroups' => AgeGroup::query()->orderBy('min_age')->get(),
            'levels' => SkillLevel::query()->orderBy('position')->get(),
            'levelOptions' => SkillLevel::query()->orderBy('position')->pluck('name', 'id')->all(),
        ])->title(__('Booking'));
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }
}
