<?php

namespace App\Domain\Booking;

use App\Enums\TimeslotKey;
use App\Models\PriceTable;
use App\Models\Sport;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The result of checking one cart item against all rules: either a price
 * with its lesson dates, or the list of problems (field => message).
 */
final class Quote
{
    /** @var array<string, string> */
    public array $errors = [];

    /**
     * Students whose age is outside the course's age groups and who have
     * not been confirmed by the office yet: id => message.
     *
     * @var array<int, string>
     */
    public array $ageWarnings = [];

    public ?PriceTable $priceTable = null;

    public ?Sport $sport = null;

    public ?TimeslotKey $option = null;

    /** @var Collection<int, CarbonImmutable> */
    public Collection $lessonDates;

    public ?string $startTime = null;

    public ?string $endTime = null;

    public int $units = 0;

    public int $priceCents = 0;

    /**
     * id => ['student' => Student, 'age' => int, 'age_override' => bool]
     *
     * @var array<int, array{student: Student, age: int, age_override: bool}>
     */
    public array $participants = [];

    public function __construct(public array $item)
    {
        $this->lessonDates = collect();
    }

    public function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->errors === [] && $this->ageWarnings === [];
    }

    public function startDate(): ?CarbonImmutable
    {
        return $this->lessonDates->first();
    }

    public function endDate(): ?CarbonImmutable
    {
        return $this->lessonDates->last();
    }

    /** True when closed days left fewer lessons than the booked days. */
    public function hasFewerLessons(): bool
    {
        return $this->priceTable && ! $this->priceTable->course_type->isHourly()
            && $this->lessonDates->count() < $this->units;
    }
}
