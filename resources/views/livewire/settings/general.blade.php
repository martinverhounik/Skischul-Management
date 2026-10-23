<x-settings.layout :status="$status" section="general">
    <x-ui.card :title="__('General')">
        <form wire:submit="save" class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <x-ui.input :label="__('Name of the ski school')" name="name" wire:model="name" required />
            </div>
            <x-ui.select :label="__('Default language')" name="locale" wire:model="locale" :options="['de' => 'Deutsch', 'en' => 'English']" required />
            <x-ui.select :label="__('Time zone')" name="timezone" wire:model="timezone" :options="$timezones" required />
            <x-ui.select :label="__('Weeks start on')" name="week_starts_on" wire:model="week_starts_on" :options="$weekdays" required />
            <x-ui.select :label="__('Add-on for lunch supervision')" name="lunch_add_on_id" wire:model="lunch_add_on_id" :options="['' => __('None')] + $addOns" />

            <label class="flex items-start gap-3 rounded-xl bg-white p-4 text-sm md:col-span-2">
                <input type="checkbox" wire:model="enforce_full_day_price" class="mt-0.5 size-4 accent-ink">
                <span class="flex flex-col gap-1">
                    <span class="font-semibold">{{ __('Full day must cost more than a half day') }}</span>
                    <span class="text-muted">{{ __('When this is on, price tables warn if a full-day price is not higher than the more expensive half day.') }}</span>
                </span>
            </label>

            <div class="md:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-settings.layout>
