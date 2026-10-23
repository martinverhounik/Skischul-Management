<?php

namespace App\Livewire\Settings;

use App\Models\AddOn;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Extras such as lunch or rental (US-08). An add-on used in a booking
 * cannot be deleted, only deactivated.
 */
class AddOns extends ListSettings
{
    protected function model(): string
    {
        return AddOn::class;
    }

    protected function blank(): array
    {
        return ['name' => '', 'price' => '', 'is_active' => true];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (Money::parse((string) $value) === null) {
                    $fail(__('Please enter a price such as 12,50.'));
                }
            }],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['name' => __('Name'), 'price' => __('Price')];
    }

    protected function toForm(Model $record): array
    {
        return ['name' => $record->name, 'price' => Money::toInput($record->price_cents), 'is_active' => $record->is_active];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'name' => $form['name'],
            'price_cents' => Money::parse($form['price']),
            'is_active' => (bool) ($form['is_active'] ?? true),
        ];
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderByDesc('is_active')->orderBy('name');
    }

    public function toggleActive(int $id): void
    {
        $addOn = $this->find($id);
        $addOn->update(['is_active' => ! $addOn->is_active]);
        $this->refresh();
    }

    public function render()
    {
        return view('livewire.settings.add-ons', [
            'lunchAddOnId' => $this->school()->lunch_add_on_id,
        ])->title(__('Add-ons'));
    }
}
