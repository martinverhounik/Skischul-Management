<?php

namespace App\Livewire\Settings\PriceTables;

use App\Livewire\Concerns\ConfirmsChanges;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\PriceTable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** All price tables of the school (US-07). Owner only (US-27). */
class Index extends Component
{
    use ConfirmsChanges, RequiresAdmin;

    /** @return Collection<int, PriceTable> */
    #[Computed]
    public function tables(): Collection
    {
        return PriceTable::query()
            ->with('sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices')
            ->orderByDesc('is_active')
            ->orderBy('course_type')
            ->orderBy('id')
            ->get();
    }

    public function toggleActive(int $id): void
    {
        $table = PriceTable::query()->findOrFail($id);
        $table->update(['is_active' => ! $table->is_active]);
        unset($this->tables);
        $this->saved();
    }

    public function delete(int $id): void
    {
        PriceTable::query()->findOrFail($id)->deleteIfUnused();
        unset($this->tables);
        $this->saved(__('Deleted.'));
    }

    public function render()
    {
        return view('livewire.settings.price-tables.index', [
            'timeslots' => $this->school()->timeslots()->get()->keyBy(fn ($t) => $t->key->value),
        ])->title(__('Price tables'));
    }
}
