<x-settings.layout :status="$status" section="skill-levels">
    <x-ui.card :title="__('Skill levels')">
        <p class="max-w-prose text-sm text-muted">{{ __('Put the levels in order from beginner (top) to advanced (bottom). This order is used everywhere in the app.') }}</p>

        <form wire:submit="create" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_120px_auto] sm:items-start">
            <x-ui.input :label="__('New skill level')" name="new.name" id="new-name" wire:model="new.name" required :placeholder="__('e.g. Blue')" />
            <x-ui.input :label="__('Colour')" name="new.color" id="new-color" type="color" wire:model="new.color" class="p-1" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        <ol class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
            @forelse ($this->records as $record)
                <li wire:key="level-{{ $record->id }}" class="px-4 py-2.5">
                    @if ($editingId === $record->id)
                        <form wire:submit="update" class="grid gap-3 sm:grid-cols-[1fr_120px_auto_auto] sm:items-start">
                            <x-ui.input :label="__('Name')" name="edit.name" id="edit-name" wire:model="edit.name" required />
                            <x-ui.input :label="__('Colour')" name="edit.color" id="edit-color" type="color" wire:model="edit.color" class="p-1" />
                            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="sm:mt-[22px]" wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                        </form>
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex items-center gap-3">
                                <span class="w-5 text-right text-sm tabular-nums text-muted">{{ $loop->iteration }}</span>
                                <span aria-hidden="true" class="size-3.5 rounded-full ring-1 ring-black/10" style="background: {{ $record->color ?? '#d9d7d0' }}"></span>
                                <span class="font-semibold">{{ $record->name }}</span>
                            </span>
                            <x-settings.row-actions :record="$record" :label="$record->name" sortable :first="$loop->first" :last="$loop->last" />
                        </div>
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-sm text-muted">{{ __('Nothing here yet.') }}</li>
            @endforelse
        </ol>
    </x-ui.card>
</x-settings.layout>
