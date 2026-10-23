<?php

namespace App\Livewire\Settings;

use App\Models\MeetingPoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Meeting points with an optional map link (US-05). */
class MeetingPoints extends ListSettings
{
    protected function model(): string
    {
        return MeetingPoint::class;
    }

    protected function blank(): array
    {
        return ['name' => '', 'map_url' => ''];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150',
                Rule::unique('meeting_points', 'name')->where('school_id', $this->school()->id)->ignore($record)],
            'map_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('Name'), 'map_url' => __('Map link')];
    }

    protected function toForm(Model $record): array
    {
        return ['name' => $record->name, 'map_url' => $record->map_url ?? ''];
    }

    protected function toAttributes(array $form): array
    {
        return ['name' => $form['name'], 'map_url' => $form['map_url'] ?: null];
    }

    public function render()
    {
        return view('livewire.settings.meeting-points')->title(__('Meeting points'));
    }
}
