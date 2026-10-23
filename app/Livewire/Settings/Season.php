<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\ConfirmsChanges;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\ClosedDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Season dates and closed days (US-01). Editable at any time. */
class Season extends Component
{
    use ConfirmsChanges, RequiresAdmin;

    public ?string $season_starts_on = null;

    public ?string $season_ends_on = null;

    public ?string $closed_from = null;

    public ?string $closed_until = null;

    public string $closed_note = '';

    public function mount(): void
    {
        $school = $this->school();
        $this->season_starts_on = $school->season_starts_on?->toDateString();
        $this->season_ends_on = $school->season_ends_on?->toDateString();
    }

    /** @return Collection<int, ClosedDay> */
    #[Computed]
    public function closedDays(): Collection
    {
        return ClosedDay::query()->orderBy('date')->get();
    }

    public function saveSeason(): void
    {
        $validated = $this->validate([
            'season_starts_on' => ['required', 'date'],
            'season_ends_on' => ['required', 'date', 'after_or_equal:season_starts_on'],
        ], attributes: [
            'season_starts_on' => __('Season start'),
            'season_ends_on' => __('Season end'),
        ]);

        $this->school()->update($validated);
        $this->saved();
    }

    public function addClosedDays(): void
    {
        $this->validate([
            'closed_from' => ['required', 'date'],
            'closed_until' => ['nullable', 'date', 'after_or_equal:closed_from'],
            'closed_note' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'closed_from' => __('Closed on'),
            'closed_until' => __('Closed until'),
            'closed_note' => __('Note'),
        ]);

        $from = CarbonImmutable::parse($this->closed_from);
        $until = $this->closed_until ? CarbonImmutable::parse($this->closed_until) : $from;

        if ($from->diffInDays($until) > 60) {
            $this->addError('closed_until', __('Please add at most 60 days at once.'));

            return;
        }

        foreach (CarbonPeriod::create($from, $until) as $date) {
            // whereDate: SQLite stores the date with a time part.
            $day = ClosedDay::query()->whereDate('date', $date->toDateString())->first()
                ?? new ClosedDay(['date' => $date->toDateString()]);
            $day->note = $this->closed_note ?: $day->note;
            $day->save();
        }

        $this->reset('closed_from', 'closed_until', 'closed_note');
        unset($this->closedDays);
        $this->saved();
    }

    public function removeClosedDay(int $id): void
    {
        ClosedDay::query()->findOrFail($id)->delete();
        unset($this->closedDays);
        $this->saved(__('Closed day removed.'));
    }

    public function render()
    {
        return view('livewire.settings.season')->title(__('Season & closed days'));
    }
}
