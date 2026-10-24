{{-- One calendar block. $entry, $compact (one line, for month and all-day rows), optional $style. --}}
@php
    $kind = $entry->kind;
    $tone = match (true) {
        $kind === 'event' => 'bg-ink text-white hover:bg-ink-soft',
        $kind === 'ungrouped' => 'border border-dashed border-signal bg-white text-ink hover:bg-paper',
        $entry->needsInstructor() => 'border-l-4 border-signal bg-white text-ink ring-1 ring-hairline hover:bg-paper',
        $kind === 'private' => 'border-l-4 border-ink bg-sand text-ink hover:bg-[#cfcdc5]',
        default => 'border-l-4 border-ink bg-white text-ink ring-1 ring-hairline hover:bg-paper',
    };
    $who = match (true) {
        $kind === 'ungrouped' => $entry->detail,
        $kind === 'event' => $entry->instructorName ?? __('Everyone'),
        default => $entry->instructorName ?? __('No instructor yet'),
    };
    $time = $entry->allDay ? __('all day') : $entry->from.'–'.$entry->to;
@endphp
<button type="button" wire:key="e-{{ $entry->key() }}"
        wire:click="open('{{ $kind }}', {{ $entry->id ?? 'null' }}, '{{ $entry->date->toDateString() }}')"
        @if ($style ?? null) style="{{ $style }}" @endif
        title="{{ $time }} · {{ $entry->title }} · {{ $who }}"
        class="{{ $tone }} {{ ($style ?? null) ? 'absolute' : 'w-full' }} flex min-w-0 flex-col overflow-hidden rounded-md px-1.5 py-1 text-left text-xs leading-tight">
    @if ($compact ?? false)
        <span class="truncate"><span class="tabular-nums">{{ $entry->allDay ? '' : $entry->from.' ' }}</span><span class="font-medium">{{ $entry->title }}</span></span>
    @elseif ($short ?? false)
        <span class="truncate font-medium">{{ $entry->title }}</span>
        <span class="truncate"><span class="tabular-nums opacity-80">{{ $entry->from }}</span> · <span class="{{ $entry->needsInstructor() ? 'font-semibold text-signal' : 'opacity-80' }}">{{ $who }}</span></span>
    @else
        <span class="truncate font-medium">{{ $entry->title }}</span>
        <span class="truncate tabular-nums opacity-80">{{ $time }}</span>
        <span class="truncate {{ $entry->needsInstructor() ? 'font-semibold text-signal' : 'opacity-80' }}">{{ $who }}</span>
    @endif
</button>
