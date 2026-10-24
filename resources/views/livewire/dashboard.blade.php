@use('App\Support\Money')
@php
    $hourHeight = 48;
    $columns = $view === 'day' ? collect([$day->toDateString() => $days->first()]) : $days;
    $field = 'h-11 rounded-xl border border-line bg-white px-3 text-sm';
@endphp
<div class="flex flex-col gap-5">
    {{-- This week's numbers (US-25) --}}
    <section aria-labelledby="kpi-title" class="flex flex-col gap-3">
        <h1 id="kpi-title" class="sr-only">{{ __('This week') }}</h1>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach ([
                [__('Active bookings this week'), $numbers->activeThisWeek(), null],
                [__('Active bookings today'), $numbers->activeToday(), null],
                [__('Paid this week'), $numbers->paidThisWeek(), null],
                [__('Unpaid this week'), $numbers->unpaidThisWeek(), $numbers->unpaidThisWeek() > 0 ? Money::format($numbers->unpaidCentsThisWeek()).' '.__('open') : null],
            ] as [$label, $value, $hint])
                <div class="flex flex-col gap-1 rounded-[var(--radius-card)] bg-paper p-4">
                    <span class="label-caps">{{ $label }}</span>
                    <span class="font-display-narrow text-4xl tabular-nums {{ $hint ? 'text-signal' : '' }}">{{ $value }}</span>
                    @if ($hint)
                        <span class="text-xs text-muted">{{ $hint }}</span>
                    @endif
                </div>
            @endforeach
            <a href="{{ route('booking') }}" class="col-span-2 flex flex-col justify-between gap-3 rounded-[var(--radius-card)] bg-ink p-4 text-white hover:bg-ink-soft lg:col-span-1">
                <span class="label-caps text-white/70">{{ __('Quick booking') }}</span>
                <span class="flex items-center justify-between gap-2 font-display-narrow text-3xl">
                    {{ __('New booking') }}
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </span>
            </a>
        </div>
        <p class="px-1 text-xs text-muted">
            {{ __('Week :from – :to: bookings with at least one lesson in this week, without cancelled ones.', ['from' => $numbers->weekStart->isoFormat('dd D.M.'), 'to' => $numbers->weekEnd->isoFormat('dd D.M.')]) }}
        </p>

        @php
            $unpaid = $numbers->unpaidList();
        @endphp
        @if ($unpaid->isNotEmpty())
            <details class="rounded-[var(--radius-card)] bg-paper px-4 py-3">
                <summary class="cursor-pointer text-sm font-medium">{{ __('Still unpaid this week') }} ({{ $numbers->unpaidThisWeek() }})</summary>
                <ul class="mt-2 flex flex-col divide-y divide-hairline rounded-xl bg-white">
                    @foreach ($unpaid as $booking)
                        <li>
                            <a href="{{ route('bookings.show', $booking) }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm hover:bg-paper">
                                <span><span class="font-medium tabular-nums">{{ $booking->number }}</span> · {{ $booking->booker_name }}</span>
                                <span class="tabular-nums">{{ Money::format($booking->total_cents) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    {{-- Planning calendar (US-26) --}}
    <section aria-labelledby="cal-title" class="flex flex-col gap-4 rounded-[var(--radius-card)] bg-paper p-3 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="previous" aria-label="{{ __('Back') }}" class="flex size-11 items-center justify-center rounded-full bg-white hover:bg-sand">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <button type="button" wire:click="next" aria-label="{{ __('Forward') }}" class="flex size-11 items-center justify-center rounded-full bg-white hover:bg-sand">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                </button>
                <button type="button" wire:click="today" class="h-9 rounded-full px-4 text-sm hover:bg-white">{{ __('Today') }}</button>
                <h2 id="cal-title" class="font-display-narrow text-2xl sm:text-3xl">
                    @if ($view === 'day')
                        {{ $day->isoFormat('dddd, D. MMMM YYYY') }}
                    @elseif ($view === 'week')
                        {{ $from->isoFormat('D. MMM') }} – {{ $to->isoFormat('D. MMM YYYY') }}
                    @else
                        {{ $day->isoFormat('MMMM YYYY') }}
                    @endif
                </h2>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="cal-date">{{ __('Date') }}</label>
                <input id="cal-date" type="date" wire:model.live="date" class="h-9 rounded-full border-0 bg-white px-3 text-sm">
                <div class="flex rounded-full bg-white p-1" role="group" aria-label="{{ __('View') }}">
                    @foreach (['day' => __('Day'), 'week' => __('Week'), 'month' => __('Month')] as $v => $label)
                        <button type="button" wire:click="$set('view', '{{ $v }}')" aria-pressed="{{ $view === $v ? 'true' : 'false' }}"
                                class="h-9 rounded-full px-4 text-sm font-medium {{ $view === $v ? 'bg-ink text-white' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="flex min-w-0 flex-col gap-1">
                <label for="f-course" class="label-caps">{{ __('Course') }}</label>
                <select id="f-course" wire:model.live="course" class="{{ $field }} h-9 max-w-[260px]">
                    <option value="">{{ __('All courses') }}</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex min-w-0 flex-col gap-1">
                <label for="f-instructor" class="label-caps">{{ __('Instructor') }}</label>
                <select id="f-instructor" wire:model.live="instructor" class="{{ $field }} h-9 max-w-[220px]">
                    <option value="">{{ __('All instructors') }}</option>
                    @foreach ($instructors as $i)
                        <option value="{{ $i->id }}">{{ $i->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            @if ($course !== '' || $instructor !== '')
                <button type="button" wire:click="resetFilters" class="h-9 rounded-full px-3 text-sm underline">{{ __('Reset filters') }}</button>
            @endif
            <x-ui.button type="button" class="ml-auto h-9" wire:click="newEvent('{{ $day->toDateString() }}')">{{ __('+ Entry') }}</x-ui.button>
        </div>

        @if ($status)
            <div role="status" class="rounded-xl bg-ink px-4 py-2.5 text-sm text-white">{{ $status }}</div>
        @endif

        @if ($view === 'month')
            @include('livewire.dashboard.month')
        @else
            @include('livewire.dashboard.grid')
        @endif

        <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="{{ __('Legend') }}">
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm border-l-4 border-ink bg-white ring-1 ring-line"></span>{{ __('Group') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm border-l-4 border-ink bg-sand"></span>{{ __('Private lesson') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm border-l-4 border-signal bg-white"></span>{{ __('No instructor yet') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-ink"></span>{{ __('Entry (day off, meeting …)') }}</li>
            <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-[repeating-linear-gradient(45deg,#e3e5e7_0_3px,transparent_3px_6px)] ring-1 ring-hairline"></span>{{ __('Closed / off season') }}</li>
        </ul>
    </section>

    @if ($selected || $eventId !== null)
        @include('livewire.dashboard.panel')
    @endif
</div>
