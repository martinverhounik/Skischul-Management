@php
    $hourly = $type->isHourly();
    $chip = fn (bool $on) => $on ? 'bg-ink text-white ring-ink' : 'bg-white text-ink ring-line hover:bg-paper';
@endphp
<x-settings.layout section="price-tables">
    <form wire:submit="save" class="flex flex-col gap-5">
        <div class="flex flex-wrap items-end justify-between gap-3 px-1">
            <div class="flex flex-col gap-1">
                <a href="{{ route('settings.price-tables') }}" wire:navigate class="label-caps hover:text-signal">← {{ __('Price tables') }}</a>
                <h1 class="font-display-narrow text-4xl">{{ $priceTable ? __('Edit price table') : __('Add a price table') }}</h1>
            </div>
        </div>

        @if ($errors->any())
            <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal">
                {{ trans_choice('Please check the marked field.|Please check the :count marked fields.', $errors->count()) }}
            </div>
        @endif

        {{-- 1. Course type --}}
        <x-ui.card :title="__('1. Course type')">
            <fieldset class="grid gap-3 sm:grid-cols-3">
                <legend class="sr-only">{{ __('Course type') }}</legend>
                @foreach ($types as $option)
                    <label wire:key="type-{{ $option->value }}" class="flex cursor-pointer flex-col gap-1 rounded-xl p-4 ring-1 {{ $chip($type === $option) }}">
                        <input type="radio" name="course_type" value="{{ $option->value }}" wire:model.live="form.course_type" class="sr-only">
                        <span class="font-semibold">{{ $option->label() }}</span>
                        <span class="text-xs {{ $type === $option ? 'text-white/80' : 'text-muted' }}">
                            {{ $option->isHourly() ? __('Priced by hours, plus a fee per extra participant') : __('Priced by days, per participant') }}
                        </span>
                    </label>
                @endforeach
            </fieldset>
        </x-ui.card>

        {{-- 2. Who it is for --}}
        <x-ui.card :title="__('2. Who it is for')">
            @foreach ([
                ['field' => 'sport_ids', 'label' => __('Sports'), 'items' => $sports, 'text' => fn ($s) => $s->name],
                ['field' => 'age_group_ids', 'label' => __('Age groups'), 'items' => $ageGroups, 'text' => fn ($a) => $a->name.' ('.$a->min_age.'–'.$a->max_age.')'],
                ['field' => 'skill_level_ids', 'label' => __('Skill levels'), 'items' => $skillLevels, 'text' => fn ($l) => $l->name],
            ] as $group)
                <fieldset class="flex flex-col gap-2">
                    <legend class="label-caps mb-2">{{ $group['label'] }} *</legend>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($group['items'] as $item)
                            @php($on = in_array((string) $item->id, array_map('strval', $form[$group['field']] ?? []), true))
                            <label wire:key="{{ $group['field'] }}-{{ $item->id }}" class="flex h-10 cursor-pointer items-center gap-2 rounded-full px-4 text-sm ring-1 {{ $chip($on) }}">
                                <input type="checkbox" value="{{ $item->id }}" wire:model.live="form.{{ $group['field'] }}" class="sr-only">
                                @if ($group['field'] === 'skill_level_ids' && $item->color)
                                    <span aria-hidden="true" class="size-2.5 rounded-full ring-1 ring-white/50" style="background: {{ $item->color }}"></span>
                                @endif
                                {{ $group['text']($item) }}
                            </label>
                        @empty
                            <p class="text-sm text-muted">{{ __('Nothing here yet.') }}</p>
                        @endforelse
                    </div>
                    @error('form.'.$group['field'])<p class="text-sm text-signal">{{ $message }}</p>@enderror
                    @error('form.'.$group['field'].'.*')<p class="text-sm text-signal">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
        </x-ui.card>

        {{-- 3. Participants, time options, days / hours --}}
        <x-ui.card :title="__('3. Rules')">
            <div class="grid gap-4 sm:grid-cols-2 lg:max-w-xl">
                <x-ui.input :label="__('Minimum participants')" name="form.min_participants" id="min-participants" type="number" min="1" wire:model="form.min_participants" required />
                <x-ui.input :label="__('Maximum participants')" name="form.max_participants" id="max-participants" type="number" min="1" wire:model="form.max_participants" required />
                <p class="text-xs text-muted sm:col-span-2">{{ __('Only used for planning instructors. Bookings are never blocked by these numbers.') }}</p>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="label-caps mb-2">{{ __('Time options') }} *</legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (App\Domain\Pricing\PriceTableForm::OPTIONS as $key)
                        @php($on = (bool) ($form['options'][$key]['active'] ?? false))
                        @php($slot = $timeslots[$key] ?? null)
                        <div wire:key="option-{{ $key }}" class="flex flex-col gap-3 rounded-xl p-4 ring-1 {{ $on ? 'bg-white ring-ink' : 'bg-white/60 ring-line' }}">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="checkbox" wire:model.live="form.options.{{ $key }}.active" class="size-4 accent-ink">
                                <span class="flex flex-col">
                                    <span class="font-semibold">{{ App\Enums\TimeslotKey::from($key)->label() }}</span>
                                    @if ($slot)<span class="text-xs tabular-nums text-muted">{{ $slot->startsAtLabel() }}–{{ $slot->endsAtLabel() }}</span>@endif
                                </span>
                            </label>
                            @if ($hourly && $on)
                                <x-ui.input :label="__('Minimum hours')" name="form.options.{{ $key }}.min_hours" id="min-hours-{{ $key }}" type="number" min="1" wire:model.live.debounce.500ms="form.options.{{ $key }}.min_hours" required />
                            @endif
                        </div>
                    @endforeach
                </div>
                @error('form.options')<p class="text-sm text-signal">{{ $message }}</p>@enderror
            </fieldset>

            @if ($hourly)
                <div class="lg:max-w-[260px]">
                    <x-ui.input :label="__('Longest lesson (hours)')" name="form.max_hours" id="max-hours" type="number" min="1" max="10" wire:model.live.debounce.500ms="form.max_hours" required :hint="__('Number of rows in the price grid')" />
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:max-w-xl">
                    <x-ui.input :label="__('Minimum days')" name="form.min_days" id="min-days" type="number" min="1" max="14" wire:model.live.debounce.500ms="form.min_days" required />
                    <x-ui.input :label="__('Maximum days')" name="form.max_days" id="max-days" type="number" min="1" max="14" wire:model.live.debounce.500ms="form.max_days" required />
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="label-caps mb-2">{{ __('Allowed start days') }} *</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($weekdays as $day => $short)
                            @php($on = in_array((string) $day, array_map('strval', $form['start_weekdays'] ?? []), true))
                            <label wire:key="start-{{ $day }}" class="flex size-11 cursor-pointer items-center justify-center rounded-full text-sm font-medium ring-1 {{ $chip($on) }}">
                                <input type="checkbox" value="{{ $day }}" wire:model.live="form.start_weekdays" class="sr-only" aria-label="{{ $weekdayNames[$day] }}">
                                <span aria-hidden="true">{{ $short }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.start_weekdays')<p class="text-sm text-signal">{{ $message }}</p>@enderror
                </fieldset>

                <div class="lg:max-w-[260px]">
                    <x-ui.select :label="__('Course ends at the latest on')" name="form.end_weekday" id="end-weekday" wire:model="form.end_weekday" :options="$weekdayNames" required />
                </div>
            @endif
        </x-ui.card>

        {{-- 4. Price grid --}}
        <x-ui.card :title="__('4. Prices')">
            <p class="max-w-prose text-sm text-muted">
                @if ($hourly)
                    {{ __('One row per number of hours. For each time option enter the price for the first participant and the fee for every further participant. Rows below the minimum hours cannot be booked.') }}
                @else
                    {{ __('One row per number of days. Prices are per participant. If a closed day falls inside a course, only the days with a lesson are charged.') }}
                @endif
            </p>

            @if ($active === [] || $rows === [])
                <p class="rounded-xl bg-white px-4 py-3 text-sm text-muted">{{ __('Activate at least one time option and set the days or hours above to see the grid.') }}</p>
            @else
                <div class="relative overflow-x-auto rounded-xl bg-white">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-hairline">
                                <th scope="col" rowspan="{{ $hourly ? 2 : 1 }}" class="label-caps px-4 py-3 text-left font-normal">{{ $hourly ? __('Hours') : __('Days') }}</th>
                                @foreach ($active as $key)
                                    <th scope="colgroup" colspan="{{ $hourly ? 2 : 1 }}" class="px-3 py-3 text-left font-semibold">{{ App\Enums\TimeslotKey::from($key)->label() }}</th>
                                @endforeach
                            </tr>
                            @if ($hourly)
                                <tr class="border-b border-hairline">
                                    @foreach ($active as $key)
                                        <th scope="col" class="label-caps px-3 pb-2 text-left font-normal">{{ __('1st person') }}</th>
                                        <th scope="col" class="label-caps px-3 pb-2 text-left font-normal">{{ __('Each further') }}</th>
                                    @endforeach
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @foreach ($rows as $units)
                                <tr wire:key="row-{{ $units }}" class="border-b border-hairline last:border-0">
                                    <th scope="row" class="whitespace-nowrap px-4 py-2 text-left font-semibold tabular-nums">{{ $type->unitLabel($units) }}</th>
                                    @foreach ($active as $key)
                                        @foreach ($hourly ? ['price', 'extra'] : ['price'] as $column)
                                            @php($field = "form.prices.{$key}.{$units}.{$column}")
                                            <td class="px-3 py-2 align-top">
                                                @if (App\Domain\Pricing\PriceTableForm::cellIsBookable($form, $key, $units))
                                                    <label class="sr-only" for="{{ $field }}">{{ App\Enums\TimeslotKey::from($key)->label() }}, {{ $type->unitLabel($units) }}{{ $column === 'extra' ? ', '.__('Each further') : '' }}</label>
                                                    <div class="relative min-w-[96px]">
                                                        <span aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">€</span>
                                                        <input id="{{ $field }}" type="text" inputmode="decimal" wire:model="{{ $field }}"
                                                               @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                                                               class="h-10 w-full rounded-lg border bg-white pl-7 pr-2 text-right tabular-nums @error($field) border-signal @else border-line @enderror">
                                                    </div>
                                                    @error($field)<p id="{{ $field }}-error" class="mt-1 max-w-[180px] text-xs text-signal">{{ $message }}</p>@enderror
                                                @else
                                                    <span class="block py-2 text-center text-muted" title="{{ __('Below the minimum hours') }}">–</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        {{-- 5. Name and description --}}
        <x-ui.card :title="__('5. Name & description')">
            <div class="grid gap-4 lg:max-w-2xl">
                <x-ui.input :label="__('Course name')" name="form.name" id="name" wire:model="form.name" :hint="__('Optional. If empty, the app shows sport + course type, e.g. “Ski Group course”.')" />
                <div class="flex flex-col gap-1.5">
                    <label for="description" class="label-caps">{{ __('Description') }}</label>
                    <textarea id="description" wire:model="form.description" rows="3" class="rounded-lg border border-line bg-white px-3 py-2 text-sm"></textarea>
                </div>
            </div>
        </x-ui.card>

        <div class="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-full bg-ink p-2 pl-6 text-white shadow-lg">
            <span class="mr-auto text-sm text-white/80">{{ $type->label() }}</span>
            <x-ui.button type="button" variant="ghost" class="text-white hover:bg-ink-soft" :href="route('settings.price-tables')" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="secondary" wire:loading.attr="disabled">{{ __('Save price table') }}</x-ui.button>
        </div>
    </form>
</x-settings.layout>
