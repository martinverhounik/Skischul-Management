<?php

namespace App\Livewire\Settings\PriceTables;

use App\Domain\Pricing\PriceTableForm;
use App\Domain\Pricing\PriceTableValidator;
use App\Domain\Pricing\SavePriceTable;
use App\Enums\CourseType;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\AgeGroup;
use App\Models\PriceTable;
use App\Models\SkillLevel;
use App\Models\Sport;
use Livewire\Component;

/**
 * "Add a price table" and editing it (US-07). The chosen course type decides
 * which fields and which price grid are shown. All rules live in
 * PriceTableValidator so they are the same everywhere.
 */
class Edit extends Component
{
    use RequiresAdmin;

    /** Set when editing; null when adding. */
    public ?PriceTable $priceTable = null;

    public array $form = [];

    public function mount(?PriceTable $priceTable = null): void
    {
        // Route-model binding already ran inside the user's school.
        $this->priceTable = $priceTable?->exists ? $priceTable : null;
        $this->form = $this->priceTable ? PriceTableForm::fromModel($this->priceTable) : PriceTableForm::blank();
    }

    public function save(PriceTableValidator $validator, SavePriceTable $save): void
    {
        $clean = $validator->validate($this->form, $this->school());
        $table = $save->handle($this->priceTable, $clean);

        session()->flash('status', __('Price table ":name" saved.', ['name' => $table->displayName()]));
        $this->redirectRoute('settings.price-tables', navigate: true);
    }

    public function render()
    {
        $type = PriceTableForm::type($this->form);

        return view('livewire.settings.price-tables.edit', [
            'type' => $type,
            'types' => CourseType::cases(),
            'rows' => PriceTableForm::rows($this->form),
            'active' => PriceTableForm::activeOptions($this->form),
            'sports' => Sport::query()->orderBy('position')->get(),
            'ageGroups' => AgeGroup::query()->orderBy('min_age')->get(),
            'skillLevels' => SkillLevel::query()->orderBy('position')->get(),
            'timeslots' => $this->school()->timeslots()->get()->keyBy(fn ($t) => $t->key->value),
            'weekdays' => collect([1, 2, 3, 4, 5, 6, 0])->mapWithKeys(fn ($d) => [$d => now()->startOfWeek()->addDays(($d + 6) % 7)->isoFormat('dd')])->all(),
            'weekdayNames' => collect([1, 2, 3, 4, 5, 6, 0])->mapWithKeys(fn ($d) => [$d => now()->startOfWeek()->addDays(($d + 6) % 7)->isoFormat('dddd')])->all(),
        ])->title($this->priceTable ? __('Edit price table') : __('Add a price table'));
    }
}
