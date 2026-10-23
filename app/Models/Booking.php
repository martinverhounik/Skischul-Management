<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One order by one booker (US-13). A parent can book several courses for
 * several children in one booking.
 *
 * @property int $id
 * @property string $number
 * @property BookingSource $source
 * @property string|null $external_id
 * @property string $booker_name
 * @property string $email
 * @property string|null $phone
 * @property string $street
 * @property string $house_number
 * @property string $postal_code
 * @property string $city
 * @property string $country
 * @property PaymentMethod $payment_method
 * @property PaymentStatus $payment_status
 * @property Carbon|null $paid_at
 * @property Carbon|null $handled_at
 * @property Carbon|null $cancelled_at
 * @property int|null $voucher_id
 * @property string|null $voucher_code
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $total_cents
 * @property string|null $notes
 * @property Carbon|null $confirmation_sent_at
 */
class Booking extends Model
{
    use BelongsToSchool;

    protected $attributes = ['source' => 'office', 'payment_status' => 'unpaid', 'discount_cents' => 0];

    protected $fillable = [
        'booker_name', 'email', 'phone', 'street', 'house_number', 'postal_code', 'city', 'country',
        'payment_method', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => BookingSource::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'handled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /** @return HasMany<BookingLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class)->orderBy('start_date')->orderBy('id');
    }

    /** @return HasMany<BookingAddOn, $this> */
    public function addOns(): HasMany
    {
        return $this->hasMany(BookingAddOn::class);
    }

    /** @return HasMany<BookingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class)->latest('id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    public function record(string $action, ?User $user = null, array $details = []): BookingEvent
    {
        return $this->events()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details ?: null,
        ]);
    }
}
