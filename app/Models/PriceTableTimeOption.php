<?php

namespace App\Models;

use App\Enums\TimeslotKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time option activated on a price table (full day, morning, afternoon),
 * with the minimum bookable hours for private lessons / guiding.
 *
 * Belongs to a school through its price table, so it has no school_id.
 *
 * @property TimeslotKey $timeslot_key
 * @property int|null $min_hours
 */
class PriceTableTimeOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['timeslot_key', 'min_hours'];

    protected function casts(): array
    {
        return ['timeslot_key' => TimeslotKey::class, 'min_hours' => 'integer'];
    }

    /** @return BelongsTo<PriceTable, $this> */
    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }
}
