<x-settings.layout :status="$status" section="add-ons">
    <x-ui.card :title="__('Add-ons')">
        <p class="max-w-prose text-sm text-muted">{{ __('Extras that can be added to a booking. An add-on that has been booked cannot be deleted; switch it off instead.') }}</p>

        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_160px_auto] sm:items-start">
            <x-ui.input :label="__('New add-on')" name="new.name" id="new-name" wire:model="new.name" required :placeholder="__('e.g. Lunch supervision')" />
            <x-ui.input :label="__('Price (€)')" name="new.price" id="new-price" inputmode="decimal" wire:model="new.price" required placeholder="22,00" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                <li wire:key="addon-{{ $record->id }}" class="px-4 py-2.5 {{ $record->is_active ? '' : 'text-muted' }}">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 sm:grid-cols-[1fr_160px_auto_auto] sm:items-start">
                            <x-ui.input :label="__('Name')" name="edit.name" id="edit-name" wire:model="edit.name" required />
                            <x-ui.input :label="__('Price (€)')" name="edit.price" id="edit-price" inputmode="decimal" wire:model="edit.price" required />
                            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-baseline gap-3">
                                <span class="font-semibold">{{ $record->name }}</span>
                                <span class="tabular-nums">{{ App\Support\Money::format($record->price_cents) }}</span>
                                @if ($record->id === $lunchAddOnId)
                                    <span class="rounded-full bg-sand px-2 py-0.5 text-xs">{{ __('Lunch supervision') }}</span>
                                @endif
                                @unless ($record->is_active)
                                    <span class="rounded-full bg-paper px-2 py-0.5 text-xs">{{ __('Off') }}</span>
                                @endunless
                            </span>
                            <x-settings.row-actions :record="$record" :label="$record->name">
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
        <p class="text-xs text-muted">{{ __('Which add-on is charged for lunch supervision is set under General.') }}</p>
    </x-ui.card>
</x-settings.layout>
