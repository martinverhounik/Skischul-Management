<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra in a booking, with quantity (plan question 11).
 *
 * @property int $add_on_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $total_cents
 */
class BookingAddOn extends Model
{
    public $timestamps = false;

    protected $fillable = ['add_on_id', 'quantity', 'unit_price_cents', 'total_cents'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_cents' => 'integer', 'total_cents' => 'integer'];
    }

    /** @return BelongsTo<AddOn, $this> */
    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }
}
