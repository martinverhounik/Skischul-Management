{{-- One student on this lesson day, with a "move to group" choice. --}}
<li wire:key="entry-{{ $entry->key() }}" class="flex items-center justify-between gap-2 py-1.5">
    <span class="flex min-w-0 items-center gap-2">
        <span aria-hidden="true" class="size-2.5 flex-none rounded-full ring-1 ring-black/10" style="background: {{ $entry->student->skillLevel?->color ?? '#d9d7d0' }}"></span>
        <span class="min-w-0">
            <span class="block truncate text-sm">{{ $entry->student->fullName() }}</span>
            <span class="block text-xs text-muted">{{ $entry->student->skillLevel?->name }} · {{ trans_choice(':count year|:count years', $entry->age) }}@if ($entry->student->medical_notes) · <span class="font-semibold text-signal" title="{{ $entry->student->medical_notes }}">{{ __('Medical') }}</span>@endif</span>
        </span>
    </span>
    <label class="sr-only" for="move-{{ $entry->key() }}">{{ __('Group of :name', ['name' => $entry->student->fullName()]) }}</label>
    <select id="move-{{ $entry->key() }}" wire:change="moveStudent({{ $entry->lessonDay->id }}, {{ $entry->student->id }}, $event.target.value)"
            class="h-8 max-w-[140px] flex-none rounded-lg border border-line bg-white px-1.5 text-xs">
        <option value="">{{ __('No group') }}</option>
        @foreach ($choices as $g)
            <option value="{{ $g->id }}" @selected($g->id === $current)>{{ $g->name }}@if ($g->age_group_id !== $entry->lessonDay->line->priceTable->ageGroups->first()?->id && $g->age_group_id) · {{ $g->ageGroup->name }}@endif</option>
        @endforeach
    </select>
</li>
