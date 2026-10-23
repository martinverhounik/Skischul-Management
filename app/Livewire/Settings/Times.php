<?php

namespace App\Livewire\Settings;

use App\Enums\TimeslotKey;
use App\Livewire\Concerns\ConfirmsChanges;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\Timeslot;
use Livewire\Component;

/**
 * Start and end times of the timeslots (US-06). Courses refer to the slot,
 * so a change here applies everywhere immediately.
 */
class Times extends Component
{
    use ConfirmsChanges, RequiresAdmin;

    /** @var array<string, array{starts_at: string, ends_at: string}> */
    public array $slots = [];

    public function mount(): void
    {
        foreach (TimeslotKey::cases() as $key) {
            $slot = Timeslot::query()->firstOrCreate(
                ['key' => $key],
                ['starts_at' => $key->defaultTimes()[0], 'ends_at' => $key->defaultTimes()[1]],
            );
            $this->slots[$key->value] = ['starts_at' => $slot->startsAtLabel(), 'ends_at' => $slot->endsAtLabel()];
        }
    }

    public function save(): void
    {
        $rules = [];
        $attributes = [];
        foreach (TimeslotKey::cases() as $key) {
            $k = $key->value;
            $rules["slots.{$k}.starts_at"] = ['required', 'date_format:H:i'];
            $rules["slots.{$k}.ends_at"] = ['required', 'date_format:H:i', "after:slots.{$k}.starts_at"];
            $attributes["slots.{$k}.starts_at"] = $key->label().' – '.__('start');
            $attributes["slots.{$k}.ends_at"] = $key->label().' – '.__('end');
        }

        $this->validate($rules, attributes: $attributes);

        foreach (TimeslotKey::cases() as $key) {
            Timeslot::query()->where('key', $key)->update($this->slots[$key->value]);
        }

        $this->saved();
    }

    public function render()
    {
        return view('livewire.settings.times', ['keys' => TimeslotKey::cases()])->title(__('Course times'));
    }
}
