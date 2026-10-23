<x-settings.layout :status="$status" section="price-tables">
    <x-ui.card :title="__('Price tables')">
        <x-slot:actions>
            <x-ui.button :href="route('settings.price-tables.create')" wire:navigate>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
                {{ __('Add a price table') }}
            </x-ui.button>
        </x-slot:actions>

        <p class="max-w-prose text-sm text-muted">{{ __('Each price table is one course offer in the booking screen: who it is for, when it runs and what it costs.') }}</p>

        @if ($this->tables->isEmpty())
            <div class="flex flex-col items-start gap-3 rounded-xl bg-white p-6">
                <p class="font-semibold">{{ __('No price tables yet.') }}</p>
                <p class="text-sm text-muted">{{ __('Start with your most common group course, for example the kids ski course.') }}</p>
            </div>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($this->tables as $table)
                    @php
                        $keys = $table->activeTimeslotKeys();
                        $from = $table->prices->min('price_cents');
                        $hourly = $table->course_type->isHourly();
                        $range = $hourly
                            ? collect($keys)->map(fn ($k) => $k->label().' '.__('from :count h', ['count' => $table->minHoursFor($k)]))->implode(' · ')
                            : $table->course_type->unitLabel($table->min_days).($table->max_days > $table->min_days ? ' – '.$table->course_type->unitLabel($table->max_days) : '');
                    @endphp
                    <li wire:key="table-{{ $table->id }}" class="flex flex-col gap-3 rounded-xl bg-white p-4 sm:flex-row sm:items-start sm:justify-between {{ $table->is_active ? '' : 'opacity-60' }}">
                        <div class="flex min-w-0 flex-col gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $hourly ? 'bg-ink text-white' : 'ring-1 ring-ink' }}">{{ $table->course_type->label() }}</span>
                                <a href="{{ route('settings.price-tables.edit', $table) }}" wire:navigate class="font-semibold hover:text-signal">{{ $table->displayName() }}</a>
                                @unless ($table->is_active)
                                    <span class="rounded-full bg-paper px-2 py-0.5 text-xs">{{ __('Off') }}</span>
                                @endunless
                            </div>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                                <dt class="label-caps pt-0.5">{{ __('For') }}</dt>
                                <dd>{{ $table->ageGroups->pluck('name')->implode(', ') }} · {{ $table->skillLevels->pluck('name')->implode(', ') }}</dd>
                                <dt class="label-caps pt-0.5">{{ __('When') }}</dt>
                                <dd>
                                    {{ collect($keys)->map->label()->implode(', ') }} · {{ $range }}
                                    @unless ($hourly)
                                        · {{ __('starts') }} {{ collect($table->start_weekdays)->map(fn ($d) => now()->startOfWeek()->addDays(($d + 6) % 7)->isoFormat('dd'))->implode(', ') }}
                                    @endunless
                                </dd>
                                <dt class="label-caps pt-0.5">{{ __('Price') }}</dt>
                                <dd class="tabular-nums">{{ $from !== null ? __('from :price', ['price' => App\Support\Money::format($from)]) : '–' }}{{ $hourly ? '' : ' '.__('per participant') }}</dd>
                            </dl>
                        </div>
                        <div class="flex flex-none flex-wrap items-center gap-1">
                            <x-ui.button variant="ghost" class="h-9 px-3" :href="route('settings.price-tables.edit', $table)" wire:navigate>{{ __('Edit') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="toggleActive({{ $table->id }})">{{ $table->is_active ? __('Switch off') : __('Switch on') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="h-9 px-3 text-signal" wire:click="delete({{ $table->id }})"
                                         wire:confirm="{{ __('Delete :name?', ['name' => $table->displayName()]) }}">{{ __('Delete') }}</x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-settings.layout>
