<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of a price grid: the price for N days (group) or N hours
 * (private / guiding) in one time option.
 *
 * @property TimeslotKey $timeslot_key
 * @property int $units
 * @property int $price_cents per participant (group) / first participant (private)
 * @property int|null $extra_participant_cents each further participant (private)
 */
class PriceTablePrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['timeslot_key', 'units', 'price_cents', 'extra_participant_cents'];

    protected function casts(): array
    {
        return [
            'timeslot_key' => TimeslotKey::class,
            'units' => 'integer',
            'price_cents' => 'integer',
            'extra_participant_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<PriceTable, $this> */
    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }
}
