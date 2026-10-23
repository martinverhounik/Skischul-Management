<?php

namespace App\Models;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One course in a booking: which offer, when, for whom, at what price.
 * The price is stored as booked; names (course, sport, students) are
 * always read live, so renaming them shows everywhere (US-30).
 *
 * @property int $id
 * @property int $booking_id
 * @property int $price_table_id
 * @property int $sport_id
 * @property CourseType $course_type
 * @property TimeslotKey $timeslot_key
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $units days (group) or hours (private / guiding)
 * @property int $lesson_count
 * @property string|null $start_time
 * @property int $price_cents
 */
class BookingLine extends Model
{
    use BelongsToSchool;

    // Only created by PlaceOrder, which sets school_id explicitly.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_type' => CourseType::class,
            'timeslot_key' => TimeslotKey::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'units' => 'integer',
            'lesson_count' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<PriceTable, $this> */
    public function priceTable(): BelongsTo
    {
        return $this->belongsTo(PriceTable::class);
    }

    /** @return BelongsTo<Sport, $this> */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('age_at_start', 'age_override')->orderBy('last_name');
    }

    /** @return HasMany<LessonDay, $this> */
    public function lessonDays(): HasMany
    {
        return $this->hasMany(LessonDay::class)->orderBy('date');
    }

    public function endTime(): ?string
    {
        if (! $this->start_time) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', strlen($this->start_time) === 5 ? $this->start_time.':00' : $this->start_time)
            ->addHours($this->units)->format('H:i');
    }
}
