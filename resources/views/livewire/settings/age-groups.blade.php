<x-settings.layout :status="$status" section="age-groups">
    <x-ui.card :title="__('Age groups')">
        <p class="max-w-prose text-sm text-muted">{{ __('Age groups may overlap. When booking, the age is only a guideline: the skill level decides, and the office can place a student in another age group.') }}</p>

        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_110px_110px_auto] sm:items-start">
            <x-ui.input :label="__('New age group')" name="new.name" id="new-name" wire:model="new.name" required :placeholder="__('e.g. Kids')" />
            <x-ui.input :label="__('From age')" name="new.min_age" id="new-min" type="number" min="0" max="120" wire:model="new.min_age" required />
            <x-ui.input :label="__('To age')" name="new.max_age" id="new-max" type="number" min="0" max="120" wire:model="new.max_age" required />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                <li wire:key="age-{{ $record->id }}" class="px-4 py-2.5">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 sm:grid-cols-[1fr_110px_110px_auto_auto] sm:items-start">
                            <x-ui.input :label="__('Name')" name="edit.name" id="edit-name" wire:model="edit.name" required />
                            <x-ui.input :label="__('From age')" name="edit.min_age" id="edit-min" type="number" min="0" max="120" wire:model="edit.min_age" required />
                            <x-ui.input :label="__('To age')" name="edit.max_age" id="edit-max" type="number" min="0" max="120" wire:model="edit.max_age" required />
                            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex items-baseline gap-3">
                                <span class="font-semibold">{{ $record->name }}</span>
                                <span class="text-sm tabular-nums text-muted">{{ __(':min–:max years', ['min' => $record->min_age, 'max' => $record->max_age]) }}</span>
                            </span>
                            <x-settings.row-actions :record="$record" :label="$record->name" />
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('Nothing here yet.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>
</x-settings.layout>
