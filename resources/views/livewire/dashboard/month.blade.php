{{-- Month view: one cell per day, up to three entries, click a day to open it. --}}
<div class="overflow-x-auto rounded-xl bg-white">
    <div class="grid min-w-[700px] grid-cols-7">
        @foreach ($days->take(7) as $date => $list)
            <div class="label-caps border-b border-hairline py-2 text-center">{{ \Carbon\CarbonImmutable::parse($date)->isoFormat('dd') }}</div>
        @endforeach
        @foreach ($days as $date => $list)
            @php
                $d = \Carbon\CarbonImmutable::parse($date);
                $inMonth = $d->month === $day->month;
                $open = $calendar->isOperatingDay($d);
            @endphp
            <div class="flex min-h-[112px] min-w-0 flex-col gap-1 border-b border-l border-hairline p-1 first:border-l-0 [&:nth-child(7n+1)]:border-l-0 {{ $open ? '' : 'bg-[repeating-linear-gradient(45deg,#f3f4f5_0_6px,transparent_6px_12px)]' }} {{ $inMonth ? '' : 'opacity-50' }}">
                <button type="button" wire:click="showDay('{{ $date }}')" aria-label="{{ $d->isoFormat('dddd, D. MMMM') }}"
                        class="flex size-7 items-center justify-center self-start rounded-full text-sm tabular-nums hover:bg-paper {{ $date === $today ? 'bg-signal font-semibold text-white hover:bg-signal' : '' }}">{{ $d->day }}</button>
                @foreach ($list->take(3) as $entry)
                    @include('livewire.dashboard.entry', ['entry' => $entry, 'compact' => true])
                @endforeach
                @if ($list->count() > 3)
                    <button type="button" wire:click="showDay('{{ $date }}')" class="self-start rounded px-1 text-xs font-medium hover:bg-paper">{{ __('+ :n more', ['n' => $list->count() - 3]) }}</button>
                @endif
                @php
                    $missing = $list->filter(fn ($e) => $e->needsInstructor())->count();
                @endphp
                @if ($missing > 0 && $list->count() > 3)
                    <span class="px-1 text-[10px] font-semibold text-signal">{{ trans_choice(':count without instructor|:count without instructor', $missing) }}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
