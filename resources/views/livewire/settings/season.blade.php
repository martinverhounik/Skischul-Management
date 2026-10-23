@php($school = auth()->user()->school)
<x-settings.layout :status="$status" section="season">
    <x-ui.card :title="__('Season')">
        <p class="max-w-prose text-sm text-muted">{{ __('Courses can only be booked inside the season and never on closed days. A multi-day course may span a closed day: that day simply has no lesson.') }}</p>
        <form wire:submit="saveSeason" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
            <x-ui.input :label="__('Season start')" name="season_starts_on" type="date" wire:model="season_starts_on" required />
            <x-ui.input :label="__('Season end')" name="season_ends_on" type="date" wire:model="season_ends_on" required />
            <x-ui.button class="sm:mt-[22px]">{{ __('Save') }}</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card :title="__('Closed days')">
        <form wire:submit="addClosedDays" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_1fr_1.5fr_auto] sm:items-start">
            <x-ui.input :label="__('Closed on')" name="closed_from" type="date" wire:model="closed_from" required />
            <x-ui.input :label="__('Closed until')" name="closed_until" type="date" wire:model="closed_until" :hint="__('Optional, for several days')" />
            <x-ui.input :label="__('Note')" name="closed_note" wire:model="closed_note" :placeholder="__('e.g. Christmas Eve')" />
            <x-ui.button class="sm:mt-[22px]">{{ __('Add') }}</x-ui.button>
        </form>

        @if ($this->closedDays->isEmpty())
            <p class="text-sm text-muted">{{ __('No closed days yet.') }}</p>
        @else
            <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
                @foreach ($this->closedDays as $day)
                    @php($outside = $school->hasSeason() && ($day->date->lt($school->season_starts_on) || $day->date->gt($school->season_ends_on)))
                    <li wire:key="closed-{{ $day->id }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5">
                        <span class="flex flex-wrap items-baseline gap-x-3">
                            <span class="font-semibold tabular-nums">{{ $day->date->isoFormat('dd, L') }}</span>
                            @if ($day->note)<span class="text-sm text-muted">{{ $day->note }}</span>@endif
                            @if ($outside)<span class="text-xs text-muted">({{ __('outside the season') }})</span>@endif
                        </span>
                        <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="removeClosedDay({{ $day->id }})"
                                     aria-label="{{ __('Remove :date', ['date' => $day->date->isoFormat('L')]) }}">{{ __('Remove') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-settings.layout>
