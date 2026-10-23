<?php

use App\Actions\Schools\CreateSchoolDefaults;
use App\Domain\Pricing\PriceCalculator;
use App\Domain\Pricing\PriceTableForm;
use App\Domain\Pricing\PriceTableValidator;
use App\Domain\Pricing\PricingException;
use App\Domain\Pricing\SavePriceTable;
use App\Enums\TimeslotKey;
use App\Models\AgeGroup;
use App\Models\PriceTable;
use App\Models\School;
use App\Models\SkillLevel;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->school = School::factory()->create();
    app(CreateSchoolDefaults::class)->handle($this->school);
    $this->signIn(User::factory()->forSchool($this->school)->owner()->create());

    $this->ski = Sport::where('name', 'Ski')->first();
    $this->kids = AgeGroup::where('name', 'Kids')->first();
    $this->blue = SkillLevel::where('name', 'Blau')->first();
});

/** A valid group-course form: Sun/Mon start, ends Friday, 1–6 days, morning + afternoon + full day. */
function groupForm(array $override = []): array
{
    $form = array_replace_recursive(PriceTableForm::blank(), [
        'course_type' => 'group',
        'sport_ids' => [(string) test()->ski->id],
        'age_group_ids' => [(string) test()->kids->id],
        'skill_level_ids' => [(string) test()->blue->id],
        'options' => ['full_day' => ['active' => true], 'morning' => ['active' => true], 'afternoon' => ['active' => true]],
        'min_days' => '1', 'max_days' => '6', 'start_weekdays' => [0, 1], 'end_weekday' => '5',
    ], $override);

    foreach (range(1, 6) as $d) {
        $form['prices']['morning'][$d]['price'] ??= (string) (50 * $d);
        $form['prices']['afternoon'][$d]['price'] ??= (string) (45 * $d);
        $form['prices']['full_day'][$d]['price'] ??= (string) (80 * $d);
    }

    return $form;
}

/** A valid private-lesson form: morning + full day, 1–6 hours, 2 hours minimum in the morning. */
function privateForm(array $override = []): array
{
    $form = array_replace_recursive(PriceTableForm::blank(), [
        'course_type' => 'private',
        'sport_ids' => [(string) test()->ski->id],
        'age_group_ids' => [(string) test()->kids->id],
        'skill_level_ids' => [(string) test()->blue->id],
        'options' => [
            'full_day' => ['active' => true, 'min_hours' => '4'],
            'morning' => ['active' => true, 'min_hours' => '2'],
            'afternoon' => ['active' => false],
        ],
        'max_hours' => '6',
    ], $override);

    foreach (range(1, 6) as $h) {
        $form['prices']['morning'][$h] ??= ['price' => (string) (70 * $h), 'extra' => (string) (20 * $h)];
        $form['prices']['full_day'][$h] ??= ['price' => (string) (70 * $h + 10), 'extra' => (string) (20 * $h + 5)];
    }

    return $form;
}

function saveTable(array $form): PriceTable
{
    $clean = app(PriceTableValidator::class)->validate($form, test()->school->fresh());

    return app(SavePriceTable::class)->handle(null, $clean);
}

function errorsFor(array $form): array
{
    try {
        app(PriceTableValidator::class)->validate($form, test()->school->fresh());
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

describe('saving (US-07)', function () {
    it('saves a group course with its rules and grid', function () {
        $table = saveTable(groupForm());

        expect($table->course_type->value)->toBe('group')
            ->and($table->sports->pluck('name')->all())->toBe(['Ski'])
            ->and($table->start_weekdays)->toBe([0, 1])
            ->and($table->end_weekday)->toBe(5)
            ->and($table->prices)->toHaveCount(18)
            ->and($table->priceCell(TimeslotKey::Morning, 3)->price_cents)->toBe(15000)
            ->and($table->displayName())->toBe('Ski '.__('Group course'));
    });

    it('saves a private lesson with min hours and two price columns', function () {
        $table = saveTable(privateForm());

        expect($table->minHoursFor(TimeslotKey::Morning))->toBe(2)
            // rows below the minimum hours are not stored
            ->and($table->priceCell(TimeslotKey::Morning, 1))->toBeNull()
            ->and($table->priceCell(TimeslotKey::FullDay, 3))->toBeNull()
            ->and($table->priceCell(TimeslotKey::Morning, 2)->extra_participant_cents)->toBe(4000);
    });

    it('uses the template name when one is given', function () {
        expect(saveTable(groupForm(['name' => 'Bambini Kurs']))->displayName())->toBe('Bambini Kurs');
    });

    it('round-trips through the form for editing', function () {
        $table = saveTable(privateForm());
        $form = PriceTableForm::fromModel($table);

        expect($form['options']['morning']['min_hours'])->toBe('2')
            ->and($form['prices']['morning'][2]['price'])->toBe('140,00');

        $form['prices']['morning'][2]['price'] = '150';
        $clean = app(PriceTableValidator::class)->validate($form, $this->school->fresh());
        $table = app(SavePriceTable::class)->handle($table, $clean);

        expect($table->priceCell(TimeslotKey::Morning, 2)->price_cents)->toBe(15000);
    });
});

describe('validation (US-07, US-30)', function () {
    it('needs at least one sport, age group, skill level and time option', function () {
        $form = groupForm([
            'options' => ['full_day' => ['active' => false], 'morning' => ['active' => false], 'afternoon' => ['active' => false]],
        ]);
        // (array_replace_recursive cannot empty a list, so set these directly)
        $form['sport_ids'] = $form['age_group_ids'] = $form['skill_level_ids'] = [];
        $errors = errorsFor($form);

        expect($errors)->toHaveKeys(['form.sport_ids', 'form.age_group_ids', 'form.skill_level_ids', 'form.options']);
    });

    it('only accepts this school\'s sports', function () {
        $other = School::factory()->create();
        $foreign = new Sport;
        $foreign->forceFill(['school_id' => $other->id, 'name' => 'Fremd'])->save();

        expect(errorsFor(groupForm(['sport_ids' => [(string) $foreign->id]])))->toHaveKey('form.sport_ids.0');
    });

    it('requires max participants and max days to be at least the minimum', function () {
        $errors = errorsFor(groupForm(['min_participants' => '5', 'max_participants' => '3', 'min_days' => '4', 'max_days' => '2']));

        expect($errors)->toHaveKeys(['form.max_participants', 'form.max_days']);
    });

    it('requires a price in every bookable cell', function () {
        $form = groupForm();
        $form['prices']['morning'][4]['price'] = '';
        $form['prices']['afternoon'][2]['price'] = 'gratis';

        expect(errorsFor($form))->toHaveKeys(['form.prices.morning.4.price', 'form.prices.afternoon.2.price']);
    });

    it('checks that every start day leaves room for the minimum days', function () {
        // Starting on Thursday with "end Friday" leaves only 2 days, but 3 are needed.
        $form = groupForm(['min_days' => '3']);
        $form['start_weekdays'] = [4];
        $errors = errorsFor($form);

        expect($errors)->toHaveKey('form.start_weekdays');
    });

    it('requires min hours not to exceed the max hours', function () {
        expect(errorsFor(privateForm(['options' => ['full_day' => ['min_hours' => '8']]])))
            ->toHaveKey('form.options.full_day.min_hours');
    });
});

describe('full-day price rule (US-07, US-30, plan question 9)', function () {
    it('refuses a full day that costs no more than the dearer half day', function () {
        $form = groupForm();
        $form['prices']['full_day'][2]['price'] = '100'; // morning 2 days = 100

        expect(errorsFor($form))->toHaveKey('form.prices.full_day.2.price');
    });

    it('checks the extra-participant column of private lessons too', function () {
        $form = privateForm();
        $form['prices']['full_day'][5]['extra'] = '100'; // morning extra 5 h = 100

        expect(errorsFor($form))->toHaveKey('form.prices.full_day.5.extra')
            ->not->toHaveKey('form.prices.full_day.5.price');
    });

    it('is not checked when the school switched the rule off', function () {
        $this->school->update(['enforce_full_day_price' => false]);
        $form = groupForm();
        $form['prices']['full_day'][2]['price'] = '50';

        expect(errorsFor($form))->toBe([]);
    });
});

describe('calculating prices (US-10, US-11)', function () {
    it('charges group courses per participant for the lesson days', function () {
        $table = saveTable(groupForm());

        expect(app(PriceCalculator::class)->quote($table, TimeslotKey::Morning, 5, 2))->toBe(50000);
    });

    it('charges only the lesson days when a closed day falls inside the course', function () {
        $table = saveTable(groupForm());

        // A 6-day window with one closed day = 5 lesson days = the 5-day price.
        expect(app(PriceCalculator::class)->quote($table, TimeslotKey::Morning, 5, 1))->toBe(25000);
    });

    it('uses the day rate of the shortest row when closed days leave fewer days than the minimum', function () {
        $form = groupForm(['min_days' => '3']);
        $table = saveTable($form); // 3 days morning = 150 → 50 per day

        expect(app(PriceCalculator::class)->quote($table, TimeslotKey::Morning, 2, 1))->toBe(10000);
    });

    it('charges private lessons a base price plus a fee per extra participant', function () {
        $table = saveTable(privateForm());

        // 3 hours morning: 210 base + 2 × 60 extra
        expect(app(PriceCalculator::class)->quote($table, TimeslotKey::Morning, 3, 3))->toBe(33000);
    });

    it('refuses fewer hours than the minimum', function () {
        $table = saveTable(privateForm());

        app(PriceCalculator::class)->quote($table, TimeslotKey::FullDay, 3, 1);
    })->throws(PricingException::class);

    it('refuses a time option the table does not offer', function () {
        $table = saveTable(privateForm());

        app(PriceCalculator::class)->quote($table, TimeslotKey::Afternoon, 3, 1);
    })->throws(PricingException::class);
});

describe('settings in use (US-30)', function () {
    it('cannot delete a sport, age group or skill level used by a price table', function () {
        saveTable(groupForm());

        foreach ([$this->ski, $this->kids, $this->blue] as $record) {
            expect(fn () => $record->deleteIfUnused())->toThrow(ValidationException::class);
            expect($record->fresh())->not->toBeNull();
        }
    });

    it('can delete them once no price table uses them', function () {
        $snowboard = Sport::where('name', 'Snowboard')->first();
        $snowboard->deleteIfUnused();

        expect(Sport::find($snowboard->id))->toBeNull();
    });

    it('shows a renamed sport everywhere (US-30)', function () {
        $table = saveTable(groupForm());
        $this->ski->update(['name' => 'Alpin']);

        expect($table->fresh()->displayName())->toBe('Alpin '.__('Group course'));
    });
});
