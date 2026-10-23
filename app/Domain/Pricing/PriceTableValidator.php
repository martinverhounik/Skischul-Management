<?php

namespace App\Domain\Pricing;

use App\Enums\CourseType;
use App\Models\School;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * All rules for saving a price table (US-07, US-30). Error keys match the
 * form fields ("form.prices.morning.3.price" etc.) so the screen can show
 * each message next to its field.
 *
 * Returns clean values: integers, cents, ids.
 */
class PriceTableValidator
{
    public function validate(array $form, School $school, string $prefix = 'form'): array
    {
        $type = PriceTableForm::type($form);
        $p = fn (string $field) => "{$prefix}.{$field}";

        $exists = fn (string $table) => Rule::exists($table, 'id')->where('school_id', $school->id);

        $rules = [
            'course_type' => ['required', Rule::enum(CourseType::class)],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'min_participants' => ['required', 'integer', 'min:1', 'max:200'],
            'max_participants' => ['required', 'integer', 'min:1', 'max:200', 'gte:min_participants'],
            'sport_ids' => ['required', 'array', 'min:1'],
            'sport_ids.*' => ['integer', $exists('sports')],
            'age_group_ids' => ['required', 'array', 'min:1'],
            'age_group_ids.*' => ['integer', $exists('age_groups')],
            'skill_level_ids' => ['required', 'array', 'min:1'],
            'skill_level_ids.*' => ['integer', $exists('skill_levels')],
        ];

        if ($type === CourseType::Group) {
            $rules += [
                'min_days' => ['required', 'integer', 'min:1', 'max:'.PriceTableForm::MAX_DAYS],
                'max_days' => ['required', 'integer', 'min:1', 'max:'.PriceTableForm::MAX_DAYS, 'gte:min_days'],
                'start_weekdays' => ['required', 'array', 'min:1'],
                'start_weekdays.*' => ['integer', 'between:0,6'],
                'end_weekday' => ['required', 'integer', 'between:0,6'],
            ];
        } else {
            $rules['max_hours'] = ['required', 'integer', 'min:1', 'max:'.PriceTableForm::MAX_HOURS];
            foreach (PriceTableForm::activeOptions($form) as $key) {
                $rules["options.{$key}.min_hours"] = ['required', 'integer', 'min:1', 'lte:max_hours'];
            }
        }

        $validator = Validator::make($form, $rules, [], $this->attributeNames($form));

        $validator->after(function ($v) use ($form, $type, $school) {
            $active = PriceTableForm::activeOptions($form);

            if ($active === []) {
                $v->errors()->add('options', __('Please activate at least one time option.'));

                return;
            }

            if ($type === CourseType::Group) {
                $this->checkStartAndEndDays($v, $form);
            }

            $this->checkPrices($v, $form, $type, $active, $school);
        });

        try {
            $validator->validate();
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $key => $messages) {
                $errors[$p($key)] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return $this->clean($form, $type);
    }

    /** Every allowed start day must leave room for the minimum days before the end day. */
    private function checkStartAndEndDays($v, array $form): void
    {
        if (! is_numeric($form['end_weekday'] ?? null) || ! is_numeric($form['min_days'] ?? null)) {
            return;
        }

        $end = (int) $form['end_weekday'];
        $min = (int) $form['min_days'];

        foreach ((array) ($form['start_weekdays'] ?? []) as $start) {
            $span = ((($end - (int) $start) % 7) + 7) % 7 + 1;
            if ($span < $min) {
                $v->errors()->add('start_weekdays', __('A course starting on :start can only run :span day(s) before :end, but needs at least :min.', [
                    'start' => $this->weekdayName((int) $start),
                    'end' => $this->weekdayName($end),
                    'span' => $span,
                    'min' => $min,
                ]));
            }
        }
    }

    private function checkPrices($v, array $form, CourseType $type, array $active, School $school): void
    {
        $rows = PriceTableForm::rows($form);
        $columns = $type->isHourly() ? ['price', 'extra'] : ['price'];
        $parsed = [];

        foreach ($active as $key) {
            foreach ($rows as $units) {
                if (! PriceTableForm::cellIsBookable($form, $key, $units)) {
                    continue;
                }
                foreach ($columns as $column) {
                    $raw = $form['prices'][$key][$units][$column] ?? '';
                    $cents = Money::parse((string) $raw);
                    if ($cents === null) {
                        $v->errors()->add("prices.{$key}.{$units}.{$column}", $raw === '' || $raw === null
                            ? __('Please enter a price.')
                            : __('Please enter a price such as 12,50.'));

                        continue;
                    }
                    $parsed[$key][$units][$column] = $cents;
                }
            }
        }

        // US-07 / US-30 + plan question 9: a full day must cost more than the
        // more expensive half day, if the school has that rule switched on.
        if (! $school->enforce_full_day_price || ! in_array('full_day', $active, true)) {
            return;
        }

        $halves = array_values(array_intersect(['morning', 'afternoon'], $active));
        if ($halves === []) {
            return;
        }

        foreach ($rows as $units) {
            foreach ($columns as $column) {
                $full = $parsed['full_day'][$units][$column] ?? null;
                $halfPrices = array_filter(array_map(fn ($h) => $parsed[$h][$units][$column] ?? null, $halves), fn ($x) => $x !== null);
                if ($full === null || $halfPrices === []) {
                    continue;
                }
                if ($full <= max($halfPrices)) {
                    $v->errors()->add("prices.full_day.{$units}.{$column}", __('The full day must cost more than a half day (:price).', [
                        'price' => Money::format(max($halfPrices)),
                    ]));
                }
            }
        }
    }

    private function clean(array $form, CourseType $type): array
    {
        $active = PriceTableForm::activeOptions($form);
        $rows = PriceTableForm::rows($form);
        $prices = [];

        foreach ($active as $key) {
            foreach ($rows as $units) {
                if (! PriceTableForm::cellIsBookable($form, $key, $units)) {
                    continue;
                }
                $cell = $form['prices'][$key][$units];
                $prices[] = [
                    'timeslot_key' => $key,
                    'units' => $units,
                    'price_cents' => Money::parse((string) $cell['price']),
                    'extra_participant_cents' => $type->isHourly() ? Money::parse((string) $cell['extra']) : null,
                ];
            }
        }

        $isGroup = $type === CourseType::Group;
        $weekdays = array_values(array_unique(array_map('intval', (array) ($form['start_weekdays'] ?? []))));
        sort($weekdays);

        return [
            'attributes' => [
                'course_type' => $type->value,
                'name' => trim((string) $form['name']) ?: null,
                'description' => trim((string) $form['description']) ?: null,
                'min_participants' => (int) $form['min_participants'],
                'max_participants' => (int) $form['max_participants'],
                'min_days' => $isGroup ? (int) $form['min_days'] : null,
                'max_days' => $isGroup ? (int) $form['max_days'] : null,
                'start_weekdays' => $isGroup ? $weekdays : null,
                'end_weekday' => $isGroup ? (int) $form['end_weekday'] : null,
                'max_hours' => $isGroup ? null : (int) $form['max_hours'],
            ],
            'sport_ids' => array_map('intval', $form['sport_ids']),
            'age_group_ids' => array_map('intval', $form['age_group_ids']),
            'skill_level_ids' => array_map('intval', $form['skill_level_ids']),
            'options' => array_map(fn ($key) => [
                'timeslot_key' => $key,
                'min_hours' => $isGroup ? null : (int) $form['options'][$key]['min_hours'],
            ], $active),
            'prices' => $prices,
        ];
    }

    private function attributeNames(array $form): array
    {
        $names = [
            'course_type' => __('Course type'),
            'min_participants' => __('Minimum participants'),
            'max_participants' => __('Maximum participants'),
            'sport_ids' => __('Sports'),
            'age_group_ids' => __('Age groups'),
            'skill_level_ids' => __('Skill levels'),
            'min_days' => __('Minimum days'),
            'max_days' => __('Maximum days'),
            'start_weekdays' => __('Start days'),
            'end_weekday' => __('End day'),
            'max_hours' => __('Maximum hours'),
        ];
        foreach (PriceTableForm::OPTIONS as $key) {
            $names["options.{$key}.min_hours"] = __('Minimum hours').' ('.PriceTableForm::timeslotKey($key)->label().')';
        }

        return $names;
    }

    private function weekdayName(int $day): string
    {
        return now()->startOfWeek()->addDays(($day + 6) % 7)->isoFormat('dddd');
    }
}
