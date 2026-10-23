<?php

use App\Domain\Booking\BookingRules;
use App\Enums\TimeslotKey;
use App\Models\Student;

require_once __DIR__.'/BookingSetup.php';

beforeEach(function () {
    $this->f = bookingSchool();
    $this->check = fn (array $item) => app(BookingRules::class)->check($this->f['school'], $item);
});

describe('group courses (US-10)', function () {
    it('prices a valid group course per participant', function () {
        $quote = ($this->check)(groupItem($this->f, ['students' => [['id' => $this->f['lisa']->id], ['id' => $this->f['tom']->id]]]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->lessonDates->map->toDateString()->all())->toBe(['2027-01-10', '2027-01-11', '2027-01-12', '2027-01-13', '2027-01-14'])
            ->and($quote->priceCents)->toBe(2 * 25000);
    });

    it('only starts on an allowed start day', function () {
        expect(($this->check)(groupItem($this->f, ['start_date' => '2027-01-12']))->errors)->toHaveKey('start_date'); // Tuesday
    });

    it('does not start on a closed day or outside the season', function () {
        expect(($this->check)(groupItem($this->f, ['start_date' => '2026-11-29']))->errors)->toHaveKey('start_date');
    });

    it('keeps the number of days within the price table', function () {
        expect(($this->check)(groupItem($this->f, ['days' => 7]))->errors)->toHaveKey('days');
    });

    it('skips a closed day and charges only the lessons when the week ends first', function () {
        // Sunday 20 Dec, 6 days, must end by Friday 25 Dec; 24 Dec is closed.
        $quote = ($this->check)(groupItem($this->f, ['start_date' => '2026-12-20', 'days' => 6]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->lessonDates)->toHaveCount(5)
            ->and($quote->hasFewerLessons())->toBeTrue()
            ->and($quote->priceCents)->toBe(25000); // the 5-day price
    });

    it('refuses a student whose skill level the course is not for', function () {
        $pro = Student::factory()->create(['school_id' => $this->f['school']->id, 'skill_level_id' => $this->f['black']->id, 'date_of_birth' => '2017-01-01']);

        expect(($this->check)(groupItem($this->f, ['students' => [['id' => $pro->id]]]))->errors)->toHaveKey("students.{$pro->id}");
    });

    it('warns about the age but lets the office book anyway', function () {
        // A 4-year-old "Mini" who skis blue, booked into the kids course.
        $mini = Student::factory()->create(['school_id' => $this->f['school']->id, 'skill_level_id' => $this->f['blue']->id, 'date_of_birth' => '2022-06-01']);

        $quote = ($this->check)(groupItem($this->f, ['students' => [['id' => $mini->id]]]));
        expect($quote->isValid())->toBeFalse()->and($quote->ageWarnings)->toHaveKey($mini->id)->and($quote->errors)->toBe([]);

        $quote = ($this->check)(groupItem($this->f, ['students' => [['id' => $mini->id, 'age_override' => true]]]));
        expect($quote->isValid())->toBeTrue()->and($quote->participants[$mini->id]['age_override'])->toBeTrue();
    });

    it('needs at least one participant and no archived students', function () {
        expect(($this->check)(groupItem($this->f, ['students' => []]))->errors)->toHaveKey('students');

        $this->f['tom']->forceFill(['archived_at' => now()])->save();
        expect(($this->check)(groupItem($this->f, ['students' => [['id' => $this->f['tom']->id]]]))->errors)->toHaveKey("students.{$this->f['tom']->id}");
    });

    it('does not book a switched-off course', function () {
        $this->f['kids']->update(['is_active' => false]);

        expect(($this->check)(groupItem($this->f))->errors)->toHaveKey('price_table');
    });
});

describe('private lessons (US-11, plan questions 3, 4, 8)', function () {
    it('prices a morning lesson with extra participants', function () {
        $quote = ($this->check)(privateItem($this->f, ['students' => [['id' => $this->f['lisa']->id], ['id' => $this->f['tom']->id]]]));

        expect($quote->isValid())->toBeTrue()
            ->and($quote->option)->toBe(TimeslotKey::Morning)
            ->and($quote->endTime)->toBe('12:00')
            ->and($quote->priceCents)->toBe(14000 + 4000);
    });

    it('works out afternoon and full day from the time', function () {
        expect(($this->check)(privateItem($this->f, ['start_time' => '14:00']))->option)->toBe(TimeslotKey::Afternoon)
            ->and(($this->check)(privateItem($this->f, ['start_time' => '10:00', 'hours' => 5]))->option)->toBe(TimeslotKey::FullDay);
    });

    it('enforces the minimum hours of the derived option', function () {
        // 11:00 for 3 hours covers morning and afternoon = full day, which needs 4 hours.
        expect(($this->check)(privateItem($this->f, ['start_time' => '11:00', 'hours' => 3]))->errors)->toHaveKey('hours');
    });

    it('stays inside the private lesson window', function () {
        expect(($this->check)(privateItem($this->f, ['start_time' => '15:30', 'hours' => 2]))->errors)->toHaveKey('start_time');
    });

    it('does not take place on closed days', function () {
        expect(($this->check)(privateItem($this->f, ['start_date' => '2026-12-24']))->errors)->toHaveKey('start_date');
    });
});
