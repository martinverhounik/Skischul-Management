{{-- Day and week view: a time grid like a paper planner. --}}
@php
    $count = $columns->count();
    $template = $count === 1 ? 'grid-template-columns: 52px minmax(0, 1fr)' : 'grid-template-columns: 52px repeat('.$count.', minmax(0, 1fr))';
    $height = ($endHour - $startHour) * $hourHeight;
    $hasAllDay = $columns->contains(fn ($list) => $list->contains(fn ($e) => $e->allDay));
@endphp
<div class="overflow-x-auto rounded-xl bg-white">
    <div class="{{ $count > 1 ? 'min-w-[760px]' : '' }}">
        {{-- Day headers --}}
        <div class="grid border-b border-hairline" style="{{ $template }}">
            <div></div>
            @foreach ($columns as $date => $list)
                @php
                    $d = \Carbon\CarbonImmutable::parse($date);
                    $isToday = $date === $today;
                @endphp
                <button type="button" wire:click="showDay('{{ $date }}')" @disabled($view === 'day')
                        class="flex flex-col items-center gap-0.5 py-2 hover:bg-paper disabled:hover:bg-transparent">
                    <span class="label-caps">{{ $d->isoFormat('dd') }}</span>
                    <span class="flex size-9 items-center justify-center rounded-full font-display-narrow text-xl {{ $isToday ? 'bg-signal text-white' : '' }}">{{ $d->day }}</span>
                    @unless ($calendar->isOperatingDay($d))
                        <span class="text-[10px] text-muted">{{ $calendar->isClosed($d) ? __('closed') : __('off season') }}</span>
                    @endunless
                </button>
            @endforeach
        </div>

        {{-- All-day entries --}}
        @if ($hasAllDay)
            <div class="grid border-b border-hairline" style="{{ $template }}">
                <div class="px-1 py-1.5 text-right text-[10px] text-muted">{{ __('all day') }}</div>
                @foreach ($columns as $date => $list)
                    <div class="flex min-w-0 flex-col gap-1 border-l border-hairline p-1">
                        @foreach ($list->where('allDay', true) as $entry)
                            @include('livewire.dashboard.entry', ['entry' => $entry, 'compact' => true, 'style' => null])
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Hours --}}
        <div class="grid" style="{{ $template }}">
            <div class="relative" style="height: {{ $height }}px">
                @for ($h = $startHour; $h < $endHour; $h++)
                    <span class="absolute right-2 -translate-y-1/2 text-[10px] tabular-nums text-muted" style="top: {{ ($h - $startHour) * $hourHeight }}px">{{ $h > $startHour ? sprintf('%02d:00', $h) : '' }}</span>
                @endfor
            </div>
            @foreach ($columns as $date => $list)
                @php
                    $d = \Carbon\CarbonImmutable::parse($date);
                @endphp
                <div class="relative border-l border-hairline {{ $calendar->isOperatingDay($d) ? '' : 'bg-[repeating-linear-gradient(45deg,#f3f4f5_0_6px,transparent_6px_12px)]' }}" style="height: {{ $height }}px">
                    @for ($h = $startHour + 1; $h < $endHour; $h++)
                        <div class="absolute inset-x-0 border-t border-hairline" style="top: {{ ($h - $startHour) * $hourHeight }}px"></div>
                    @endfor
                    @foreach ($layouts->get($date, []) as $placed)
                        @php
                            $e = $placed['entry'];
                            $top = ($e->startMinutes() - $startHour * 60) / 60 * $hourHeight;
                            $bh = max(22, ($e->endMinutes() - $e->startMinutes()) / 60 * $hourHeight - 2);
                            $w = 100 / $placed['lanes'];
                            $style = sprintf('top:%.1fpx;height:%.1fpx;left:calc(%.3f%% + 2px);width:calc(%.3f%% - 4px)', $top + 1, $bh, $placed['lane'] * $w, $w);
                        @endphp
                        @include('livewire.dashboard.entry', ['entry' => $e, 'compact' => $bh < 34, 'short' => $bh < 56, 'style' => $style])
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
@if ($columns->every(fn ($list) => $list->isEmpty()))
    <p class="text-sm text-muted">{{ $course !== '' || $instructor !== '' ? __('Nothing planned for this filter.') : __('Nothing planned in this period.') }}</p>
@endif
