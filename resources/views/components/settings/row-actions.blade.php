{{-- Edit / delete (and optional up/down) buttons for one settings row. --}}
@props(['record', 'label', 'sortable' => false, 'first' => false, 'last' => false])
<div class="flex flex-wrap items-center justify-end gap-1">
    @if ($sortable)
        <button type="button" wire:click="move({{ $record->id }}, 'up')" @disabled($first)
                aria-label="{{ __('Move :name up', ['name' => $label]) }}"
                class="flex size-9 items-center justify-center rounded-full hover:bg-paper disabled:opacity-30">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 15l-6-6-6 6"/></svg>
        </button>
        <button type="button" wire:click="move({{ $record->id }}, 'down')" @disabled($last)
                aria-label="{{ __('Move :name down', ['name' => $label]) }}"
                class="flex size-9 items-center justify-center rounded-full hover:bg-paper disabled:opacity-30">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
    @endif
    {{ $slot }}
    <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="startEditing({{ $record->id }})"
                 aria-label="{{ __('Edit :name', ['name' => $label]) }}">{{ __('Edit') }}</x-ui.button>
    <x-ui.button type="button" variant="ghost" class="h-9 px-3 text-signal" wire:click="delete({{ $record->id }})"
                 wire:confirm="{{ __('Delete :name?', ['name' => $label]) }}"
                 aria-label="{{ __('Delete :name', ['name' => $label]) }}">{{ __('Delete') }}</x-ui.button>
</div>
