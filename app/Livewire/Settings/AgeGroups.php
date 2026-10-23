<?php

namespace App\Livewire\Settings;

use App\Models\AgeGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Age bands with min/max age; overlaps are allowed (US-04). */
class AgeGroups extends ListSettings
{
    protected function model(): string
    {
        return AgeGroup::class;
    }

    protected function blank(): array
    {
        return ['name' => '', 'min_age' => '', 'max_age' => ''];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('age_groups', 'name')->where('school_id', $this->school()->id)->ignore($record)],
            'min_age' => ['required', 'integer', 'between:0,120'],
            'max_age' => ['required', 'integer', 'between:0,120', "gte:{$prefix}.min_age"],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('Name'), 'min_age' => __('Minimum age'), 'max_age' => __('Maximum age')];
    }

    protected function toForm(Model $record): array
    {
        return $record->only('name', 'min_age', 'max_age');
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderBy('min_age')->orderBy('max_age');
    }

    public function render()
    {
        return view('livewire.settings.age-groups')->title(__('Age groups'));
    }
}
