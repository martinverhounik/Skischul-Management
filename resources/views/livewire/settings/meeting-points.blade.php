<x-settings.layout :status="$status" section="meeting-points">
    <x-ui.card :title="__('Meeting points')">
        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_1.4fr_auto] sm:items-start">
            <x-ui.input :label="__('New meeting point')" name="new.name" id="new-name" wire:model="new.name" required :placeholder="__('e.g. Valley station')" />
            <x-ui.input :label="__('Map link')" name="new.map_url" id="new-map" type="url" wire:model="new.map_url" placeholder="https://maps.google.com/…" :hint="__('Optional')" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                <li wire:key="mp-{{ $record->id }}" class="px-4 py-2.5">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 sm:grid-cols-[1fr_1.4fr_auto_auto] sm:items-start">
                            <x-ui.input :label="__('Name')" name="edit.name" id="edit-name" wire:model="edit.name" required />
                            <x-ui.input :label="__('Map link')" name="edit.map_url" id="edit-map" type="url" wire:model="edit.map_url" />
                            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-baseline gap-3">
                                <span class="font-semibold">{{ $record->name }}</span>
                                @if ($record->map_url)
                                    <a href="{{ $record->map_url }}" target="_blank" rel="noopener noreferrer" class="text-sm underline hover:text-signal">{{ __('Open map') }}</a>
                                @endif
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
