<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History entry of a booking: who did what and when (e.g. "who handled a
 * booking", US-31). Users are never deleted, only deactivated.
 *
 * @property string $action
 * @property array|null $details
 */
class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
