<x-settings.layout :status="$status" section="sports">
    <x-ui.card :title="__('Sports')">
        <p class="max-w-prose text-sm text-muted">{{ __('The order here is the order used in lists and filters.') }}</p>

        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_auto] sm:items-start">
            <x-ui.input :label="__('New sport')" name="new.name" id="new-name" wire:model="new.name" required :placeholder="__('e.g. Cross-country')" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                <li wire:key="sport-{{ $record->id }}" class="px-4 py-2.5">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-start">
                            <x-ui.input :label="__('Name')" name="edit.name" id="edit-name" wire:model="edit.name" required />
                            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold">{{ $record->name }}</span>
                            <x-settings.row-actions :record="$record" :label="$record->name" sortable :first="$loop->first" :last="$loop->last" />
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('Nothing here yet.') }}</li>
            @endforelse
        </ul>
    </x-ui.card>
</x-settings.layout>
