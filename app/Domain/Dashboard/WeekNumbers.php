<?php

namespace App\Domain\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The dashboard numbers (US-25). "This week" = bookings with at least one
 * lesson in the school's week (default Sunday to Saturday, owner decision),
 * "today" = with a lesson today. Cancelled bookings never count.
 */
class WeekNumbers
{
    public CarbonImmutable $today;

    public CarbonImmutable $weekStart;

    public CarbonImmutable $weekEnd;

    public function __construct(private School $school, ?CarbonImmutable $today = null)
    {
        $this->today = $today ?? CarbonImmutable::parse($school->today()->toDateString());
        $this->weekStart = $this->today->subDays(($this->today->dayOfWeek - $school->week_starts_on + 7) % 7);
        $this->weekEnd = $this->weekStart->addDays(6);
    }

    public function activeThisWeek(): int
    {
        return $this->week()->count();
    }

    public function activeToday(): int
    {
        return $this->withLessonsBetween($this->today, $this->today)->count();
    }

    public function paidThisWeek(): int
    {
        return $this->week()->where('payment_status', PaymentStatus::Paid)->count();
    }

    public function unpaidThisWeek(): int
    {
        return $this->week()->where('payment_status', PaymentStatus::Unpaid)->count();
    }

    /** Amount still open this week, in cents. */
    public function unpaidCentsThisWeek(): int
    {
        return (int) $this->week()->where('payment_status', PaymentStatus::Unpaid)->sum('total_cents');
    }

    /**
     * Unpaid bookings of this week, earliest lesson first, for quick action.
     *
     * @return Collection<int, Booking>
     */
    public function unpaidList(int $limit = 8): Collection
    {
        return $this->week()->where('payment_status', PaymentStatus::Unpaid)
            ->withMin('lessonDays as first_lesson', 'date')
            ->orderBy('first_lesson')->orderBy('number')
            ->limit($limit)->get();
    }

    /** @return Builder<Booking> */
    private function week(): Builder
    {
        return $this->withLessonsBetween($this->weekStart, $this->weekEnd);
    }

    /** @return Builder<Booking> */
    private function withLessonsBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Booking::query()->whereNull('cancelled_at')
            ->whereHas('lessonDays', fn ($q) => $q
                ->whereDate('lesson_days.date', '>=', $from->toDateString())
                ->whereDate('lesson_days.date', '<=', $to->toDateString()));
    }
}
