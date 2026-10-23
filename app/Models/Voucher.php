<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\GuardsDeletion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A discount code redeemable at checkout (US-28).
 *
 * @property int $id
 * @property string $code always upper case
 * @property DiscountType $discount_type
 * @property int $discount_value cents (fixed) or hundredths of a percent (percent)
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property int|null $max_uses
 * @property int $times_used
 * @property bool $is_active
 * @property string|null $note
 */
class Voucher extends Model
{
    use BelongsToSchool, GuardsDeletion;

    /** Same defaults as the database, so new objects behave correctly before a reload. */
    protected $attributes = ['is_active' => true, 'times_used' => 0];

    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'valid_from', 'valid_until',
        'max_uses', 'is_active', 'note',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'max_uses' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function usages(): array
    {
        // A voucher that was redeemed should be switched off instead.
        return [
            __('Bookings') => fn () => Booking::query()->where('voucher_id', $this->id)->count(),
        ];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    /**
     * Why the voucher can't be used on the given day, or null if it can.
     * Checked again at checkout (US-13, US-30).
     */
    public function rejectionReason(CarbonInterface $on): ?string
    {
        // Compare calendar dates, not instants: "today" is in the school's
        // time zone, the stored dates have none.
        $day = $on->toDateString();

        return match (true) {
            ! $this->is_active => __('This voucher is no longer active.'),
            $this->valid_from && $day < $this->valid_from->toDateString() => __('This voucher is not valid yet.'),
            $this->valid_until && $day > $this->valid_until->toDateString() => __('This voucher has expired.'),
            $this->max_uses !== null && $this->times_used >= $this->max_uses => __('This voucher has already been used up.'),
            default => null,
        };
    }

    public function isRedeemable(CarbonInterface $on): bool
    {
        return $this->rejectionReason($on) === null;
    }

    /** Discount in cents for an order total; never more than the total. */
    public function discountFor(int $totalCents): int
    {
        $discount = match ($this->discount_type) {
            DiscountType::Fixed => $this->discount_value,
            DiscountType::Percent => (int) round($totalCents * $this->discount_value / 10000),
        };

        return max(0, min($discount, $totalCents));
    }
}
