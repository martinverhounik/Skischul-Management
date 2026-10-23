<?php

namespace App\Domain\Pricing;

use App\Enums\CourseType;
use App\Enums\TimeslotKey;
use App\Models\PriceTable;
use App\Support\Money;

/**
 * The shape of the price-table form (US-07), shared by the Livewire screen,
 * the validator and the save action. All values are kept as the browser
 * sends them (strings); PriceTableValidator turns them into clean values.
 */
final class PriceTableForm
{
    /** Time options a price table can activate, in display order. */
    public const OPTIONS = ['full_day', 'morning', 'afternoon'];

    public const MAX_DAYS = 14;

    public const MAX_HOURS = 10;

    public static function blank(): array
    {
        $options = [];
        foreach (self::OPTIONS as $key) {
            $options[$key] = ['active' => $key !== 'full_day', 'min_hours' => '1'];
        }

        return [
            'course_type' => 'group',
            'name' => '',
            'description' => '',
            'min_participants' => '1',
            'max_participants' => '8',
            'sport_ids' => [],
            'age_group_ids' => [],
            'skill_level_ids' => [],
            'options' => $options,
            'min_days' => '1',
            'max_days' => '6',
            'start_weekdays' => [0, 1],
            'end_weekday' => '5',
            'max_hours' => '6',
            'prices' => [],
        ];
    }

    public static function fromModel(PriceTable $table): array
    {
        $table->loadMissing('sports', 'ageGroups', 'skillLevels', 'timeOptions', 'prices');
        $form = self::blank();

        $form['course_type'] = $table->course_type->value;
        $form['name'] = $table->name ?? '';
        $form['description'] = $table->description ?? '';
        $form['min_participants'] = (string) $table->min_participants;
        $form['max_participants'] = (string) $table->max_participants;
        $form['sport_ids'] = $table->sports->pluck('id')->map(fn ($id) => (string) $id)->all();
        $form['age_group_ids'] = $table->ageGroups->pluck('id')->map(fn ($id) => (string) $id)->all();
        $form['skill_level_ids'] = $table->skillLevels->pluck('id')->map(fn ($id) => (string) $id)->all();

        foreach (self::OPTIONS as $key) {
            $option = $table->timeOptions->first(fn ($o) => $o->timeslot_key->value === $key);
            $form['options'][$key] = [
                'active' => $option !== null,
                'min_hours' => (string) ($option?->min_hours ?? 1),
            ];
        }

        if ($table->course_type === CourseType::Group) {
            $form['min_days'] = (string) $table->min_days;
            $form['max_days'] = (string) $table->max_days;
            $form['start_weekdays'] = $table->start_weekdays ?? [];
            $form['end_weekday'] = (string) $table->end_weekday;
        } else {
            $form['max_hours'] = (string) $table->max_hours;
        }

        foreach ($table->prices as $price) {
            $form['prices'][$price->timeslot_key->value][$price->units] = [
                'price' => Money::toInput($price->price_cents),
                'extra' => $price->extra_participant_cents === null ? '' : Money::toInput($price->extra_participant_cents),
            ];
        }

        return $form;
    }

    public static function type(array $form): CourseType
    {
        return CourseType::tryFrom((string) ($form['course_type'] ?? '')) ?? CourseType::Group;
    }

    /** @return list<string> active option keys in display order */
    public static function activeOptions(array $form): array
    {
        return array_values(array_filter(self::OPTIONS, fn ($k) => (bool) ($form['options'][$k]['active'] ?? false)));
    }

    /**
     * Grid rows: days min..max for group courses, hours 1..max for private.
     *
     * @return list<int>
     */
    public static function rows(array $form): array
    {
        if (self::type($form) === CourseType::Group) {
            $min = max(1, (int) ($form['min_days'] ?? 1));
            $max = min(self::MAX_DAYS, (int) ($form['max_days'] ?? $min));

            return $max >= $min ? range($min, $max) : [];
        }

        $max = min(self::MAX_HOURS, max(1, (int) ($form['max_hours'] ?? 1)));

        return range(1, $max);
    }

    /** Is this cell bookable? Hours below an option's minimum are not. */
    public static function cellIsBookable(array $form, string $key, int $units): bool
    {
        if (self::type($form) === CourseType::Group) {
            return true;
        }

        return $units >= (int) ($form['options'][$key]['min_hours'] ?? 1);
    }

    public static function timeslotKey(string $key): TimeslotKey
    {
        return TimeslotKey::from($key);
    }
}
