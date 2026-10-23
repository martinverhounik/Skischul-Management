@php
    $describe = function (App\Models\Voucher $v) {
        return $v->discount_type === App\Enums\DiscountType::Percent
            ? rtrim(rtrim(App\Support\Money::toInput($v->discount_value), '0'), ',.').' %'
            : App\Support\Money::format($v->discount_value);
    };
@endphp
<x-settings.layout :status="$status" section="vouchers">
    <x-ui.card :title="__('Vouchers')">
        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 md:grid-cols-4 md:items-start">
            <x-ui.input :label="__('Code')" name="new.code" id="new-code" wire:model="new.code" required placeholder="WINTER10" class="uppercase" />
            <x-ui.select :label="__('Type')" name="new.discount_type" id="new-type" wire:model.live="new.discount_type" :options="$types" required />
            <x-ui.input :label="$new['discount_type'] === 'percent' ? __('Discount (%)') : __('Discount (€)')" name="new.amount" id="new-amount" inputmode="decimal" wire:model="new.amount" required />
            <x-ui.input :label="__('Usage limit')" name="new.max_uses" id="new-max" type="number" min="1" wire:model="new.max_uses" :hint="__('Empty = unlimited')" />
            <x-ui.input :label="__('Valid from')" name="new.valid_from" id="new-from" type="date" wire:model="new.valid_from" />
            <x-ui.input :label="__('Valid until')" name="new.valid_until" id="new-until" type="date" wire:model="new.valid_until" />
            <x-ui.input :label="__('Note')" name="new.note" id="new-note" wire:model="new.note" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add voucher') }}</x-ui.button>
        </form>

        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                @php($problem = $record->rejectionReason($today))
                <li wire:key="voucher-{{ $record->id }}" class="px-4 py-3 {{ $problem ? 'text-muted' : '' }}">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 md:grid-cols-4 md:items-start">
                            <x-ui.input :label="__('Code')" name="edit.code" id="edit-code" wire:model="edit.code" required class="uppercase" />
                            <x-ui.select :label="__('Type')" name="edit.discount_type" id="edit-type" wire:model.live="edit.discount_type" :options="$types" required />
                            <x-ui.input :label="$edit['discount_type'] === 'percent' ? __('Discount (%)') : __('Discount (€)')" name="edit.amount" id="edit-amount" inputmode="decimal" wire:model="edit.amount" required />
                            <x-ui.input :label="__('Usage limit')" name="edit.max_uses" id="edit-max" type="number" min="1" wire:model="edit.max_uses" />
                            <x-ui.input :label="__('Valid from')" name="edit.valid_from" id="edit-from" type="date" wire:model="edit.valid_from" />
                            <x-ui.input :label="__('Valid until')" name="edit.valid_until" id="edit-until" type="date" wire:model="edit.valid_until" />
                            <x-ui.input :label="__('Note')" name="edit.note" id="edit-note" wire:model="edit.note" />
                            <div class="flex gap-2">
                                <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                                <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-col gap-0.5">
                                <span class="flex flex-wrap items-baseline gap-3">
                                    <span class="font-mono font-semibold">{{ $record->code }}</span>
                                    <span class="tabular-nums">−{{ $describe($record) }}</span>
                                    @if ($problem)
                                        <span class="rounded-full bg-paper px-2 py-0.5 text-xs">{{ $problem }}</span>
                                    @else
                                        <span class="rounded-full bg-ink px-2 py-0.5 text-xs text-white">{{ __('Valid') }}</span>
                                    @endif
                                </span>
                                <span class="text-xs text-muted">
                                    @if ($record->valid_from || $record->valid_until)
                                        {{ $record->valid_from?->isoFormat('L') ?? '…' }} – {{ $record->valid_until?->isoFormat('L') ?? '…' }} ·
                                    @endif
                                    {{ $record->max_uses ? __('used :used of :max times', ['used' => $record->times_used, 'max' => $record->max_uses]) : __('used :used times', ['used' => $record->times_used]) }}
                                    @if ($record->note) · {{ $record->note }} @endif
                                </span>
                            </span>
                            <x-settings.row-actions :record="$record" :label="$record->code">
                                <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="toggleActive({{ $record->id }})">
                                    {{ $record->is_active ? __('Switch off') : __('Switch on') }}
                                </x-ui.button>
                            </x-settings.row-actions>
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('Nothing here yet.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>
</x-settings.layout>
