<?php

namespace App\Livewire\Settings;

use App\Models\SkillLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** The school's own skill levels, in order from beginner to advanced (US-03). */
class SkillLevels extends ListSettings
{
    protected function model(): string
    {
        return SkillLevel::class;
    }

    protected function blank(): array
    {
        return ['name' => '', 'color' => '#171920'];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('skill_levels', 'name')->where('school_id', $this->school()->id)->ignore($record)],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('Name'), 'color' => __('Colour')];
    }

    protected function toForm(Model $record): array
    {
        return ['name' => $record->name, 'color' => $record->color ?? '#171920'];
    }

    protected function sortable(): bool
    {
        return true;
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderBy('position');
    }

    public function render()
    {
        return view('livewire.settings.skill-levels')->title(__('Skill levels'));
    }
}
