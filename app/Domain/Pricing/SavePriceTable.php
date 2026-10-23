<?php

namespace App\Domain\Pricing;

use App\Models\PriceTable;
use Illuminate\Support\Facades\DB;

/** Stores the clean values from PriceTableValidator in one transaction. */
class SavePriceTable
{
    public function handle(?PriceTable $table, array $clean): PriceTable
    {
        return DB::transaction(function () use ($table, $clean) {
            $table ??= new PriceTable;
            $table->fill($clean['attributes'])->save();

            $table->sports()->sync($clean['sport_ids']);
            $table->ageGroups()->sync($clean['age_group_ids']);
            $table->skillLevels()->sync($clean['skill_level_ids']);

            // The grid is small; replacing it is simpler than diffing it.
            $table->timeOptions()->delete();
            $table->timeOptions()->createMany($clean['options']);

            $table->prices()->delete();
            $table->prices()->createMany($clean['prices']);

            return $table->fresh(['sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices']);
        });
    }
}
