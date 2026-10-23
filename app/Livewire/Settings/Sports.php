<?php

namespace App\Livewire\Settings;

use App\Models\Sport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Sports the school offers (US-02). */
class Sports extends ListSettings
{
    protected function model(): string
    {
        return Sport::class;
    }

    protected function blank(): array
    {
        return ['name' => ''];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('sports', 'name')->where('school_id', $this->school()->id)->ignore($record)],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('Name')];
    }

    protected function toForm(Model $record): array
    {
        return ['name' => $record->name];
    }

    protected function sortable(): bool
    {
        return true;
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public function render()
    {
        return view('livewire.settings.sports')->title(__('Sports'));
    }
}
