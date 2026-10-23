<x-settings.layout :status="$status" section="times">
    <x-ui.card :title="__('Course times')">
        <p class="max-w-prose text-sm text-muted">{{ __('Price tables and courses use these slots. Changing a time here changes it for every course that uses the slot.') }}</p>
        <form wire:submit="save" class="flex flex-col gap-4">
            <div class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
                @foreach ($keys as $key)
                    <fieldset wire:key="slot-{{ $key->value }}" class="grid gap-3 px-4 py-3 sm:grid-cols-[1fr_140px_140px] sm:items-start">
                        <legend class="sr-only">{{ $key->label() }}</legend>
                        <span aria-hidden="true" class="pt-2 font-semibold sm:pt-8">{{ $key->label() }}</span>
                        <x-ui.input :label="__('From')" name="slots.{{ $key->value }}.starts_at" id="slot-{{ $key->value }}-start" type="time" wire:model="slots.{{ $key->value }}.starts_at" required />
                        <x-ui.input :label="__('To')" name="slots.{{ $key->value }}.ends_at" id="slot-{{ $key->value }}-end" type="time" wire:model="slots.{{ $key->value }}.ends_at" required />
                    </fieldset>
                @endforeach
            </div>
            <div><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-settings.layout>
