<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\ConfirmsChanges;
use App\Livewire\Concerns\RequiresAdmin;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** School name, language, time zone, week start and price rules. */
class General extends Component
{
    use ConfirmsChanges, RequiresAdmin;

    public string $name = '';

    public string $locale = 'de';

    public string $timezone = 'Europe/Vienna';

    public int $week_starts_on = 0;

    public bool $enforce_full_day_price = true;

    public ?int $lunch_add_on_id = null;

    public function mount(): void
    {
        $school = $this->school();
        $this->fill($school->only('name', 'locale', 'timezone', 'week_starts_on', 'enforce_full_day_price', 'lunch_add_on_id'));
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(['de', 'en'])],
            'timezone' => ['required', 'timezone'],
            'week_starts_on' => ['required', 'integer', 'between:0,6'],
            'enforce_full_day_price' => ['boolean'],
            'lunch_add_on_id' => ['nullable', Rule::exists('add_ons', 'id')->where('school_id', $this->school()->id)],
        ], attributes: [
            'name' => __('Name of the ski school'),
            'locale' => __('Default language'),
            'timezone' => __('Time zone'),
            'week_starts_on' => __('Weeks start on'),
            'lunch_add_on_id' => __('Add-on for lunch supervision'),
        ]);

        $this->school()->update($validated);
        $this->saved();
    }

    public function render()
    {
        return view('livewire.settings.general', [
            'weekdays' => collect(range(0, 6))->mapWithKeys(fn ($d) => [$d => now()->startOfWeek()->addDays(($d + 6) % 7)->isoFormat('dddd')])->all(),
            'addOns' => $this->school()->addOns()->where('is_active', true)->pluck('name', 'id')->all(),
            'timezones' => collect(\DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE))->mapWithKeys(fn ($tz) => [$tz => $tz])->all(),
        ])->title(__('Settings'));
    }
}
