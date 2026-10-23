<?php

use App\Domain\Calendar\CalendarRuleViolation;
use App\Domain\Calendar\SchoolCalendar;
use App\Models\School;
use Carbon\CarbonImmutable;

// US-01 / US-30: season and closed days are the master availability filter.

beforeEach(function () {
    $this->school = School::factory()->create([
        'season_starts_on' => '2026-12-05',
        'season_ends_on' => '2027-04-11',
    ]);
    $this->school->closedDays()->create(['date' => '2026-12-24']);
    $this->calendar = SchoolCalendar::for($this->school->fresh());
});

$day = fn (string $date) => CarbonImmutable::parse($date);

it('knows which days are in the season', function () use ($day) {
    expect($this->calendar->isInSeason($day('2026-12-04')))->toBeFalse()
        ->and($this->calendar->isInSeason($day('2026-12-05')))->toBeTrue()
        ->and($this->calendar->isInSeason($day('2027-04-11')))->toBeTrue()
        ->and($this->calendar->isInSeason($day('2027-04-12')))->toBeFalse();
});

it('treats closed days as not operating', function () use ($day) {
    expect($this->calendar->isOperatingDay($day('2026-12-24')))->toBeFalse()
        ->and($this->calendar->isOperatingDay($day('2026-12-23')))->toBeTrue();
});

it('offers nothing while no season is set', function () use ($day) {
    $school = School::factory()->create(['season_starts_on' => null, 'season_ends_on' => null]);

    expect(SchoolCalendar::for($school)->isOperatingDay($day('2026-12-23')))->toBeFalse();
});

it('does not let a course start on a closed day or outside the season', function (string $start) use ($day) {
    $this->calendar->lessonDays($day($start), 1);
})->with(['2026-12-24', '2026-12-04'])->throws(CalendarRuleViolation::class);

it('skips a closed day inside a multi-day course but keeps the window', function () use ($day) {
    // Sunday 20 Dec to Friday 25 Dec, closed on the 24th.
    $days = $this->calendar->lessonDays($day('2026-12-20'), 6)->map->toDateString()->all();

    expect($days)->toBe(['2026-12-20', '2026-12-21', '2026-12-22', '2026-12-23', '2026-12-25']);
});

it('does not let a course run past the season end', function () use ($day) {
    $this->calendar->lessonDays($day('2027-04-09'), 5);
})->throws(CalendarRuleViolation::class);
