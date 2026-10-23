<?php

namespace App\Livewire\Settings;

use App\Enums\DiscountType;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Voucher codes with a fixed or percentage discount (US-28). */
class Vouchers extends ListSettings
{
    protected function model(): string
    {
        return Voucher::class;
    }

    protected function blank(): array
    {
        return [
            'code' => '', 'discount_type' => 'percent', 'amount' => '',
            'valid_from' => '', 'valid_until' => '', 'max_uses' => '', 'note' => '',
        ];
    }

    protected function fieldRules(string $prefix, ?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('vouchers', 'code')->where('school_id', $this->school()->id)->ignore($record)],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'amount' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($prefix) {
                $number = Money::parseDecimal((string) $value);
                $type = data_get($this, "{$prefix}.discount_type");
                if ($number === null || $number <= 0) {
                    $fail(__('Please enter a discount greater than 0.'));
                } elseif ($type === 'percent' && $number > 100) {
                    $fail(__('A percentage can be at most 100.'));
                }
            }],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', "after_or_equal:{$prefix}.valid_from"],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function labels(): array
    {
        return [
            'code' => __('Code'), 'discount_type' => __('Type'), 'amount' => __('Discount'),
            'valid_from' => __('Valid from'), 'valid_until' => __('Valid until'),
            'max_uses' => __('Usage limit'), 'note' => __('Note'),
        ];
    }

    protected function toForm(Model $record): array
    {
        /** @var Voucher $record */
        return [
            'code' => $record->code,
            'discount_type' => $record->discount_type->value,
            'amount' => Money::toInput($record->discount_value),
            'valid_from' => $record->valid_from?->toDateString() ?? '',
            'valid_until' => $record->valid_until?->toDateString() ?? '',
            'max_uses' => $record->max_uses ?? '',
            'note' => $record->note ?? '',
        ];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'code' => $form['code'],
            'discount_type' => $form['discount_type'],
            // Both types are stored as hundredths: cents, or 1/100 percent.
            'discount_value' => Money::parse($form['amount']),
            'valid_from' => $form['valid_from'] ?: null,
            'valid_until' => $form['valid_until'] ?: null,
            'max_uses' => $form['max_uses'] === '' || $form['max_uses'] === null ? null : (int) $form['max_uses'],
            'note' => $form['note'] ?: null,
        ];
    }

    protected function order(Builder $query): Builder
    {
        return $query->orderByDesc('is_active')->orderBy('code');
    }

    public function toggleActive(int $id): void
    {
        $voucher = $this->find($id);
        $voucher->update(['is_active' => ! $voucher->is_active]);
        $this->refresh();
    }

    public function render()
    {
        return view('livewire.settings.vouchers', [
            'types' => collect(DiscountType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'today' => $this->school()->today(),
        ])->title(__('Vouchers'));
    }
}
