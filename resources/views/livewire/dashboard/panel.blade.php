{{-- Side panel: a course entry (instructor synced with Grouping) or a standalone entry form. --}}
<div class="fixed inset-0 z-40 flex justify-end bg-ink/30" wire:click.self="closePanel" wire:keydown.escape.window="closePanel">
    <aside role="dialog" aria-modal="true" aria-labelledby="panel-title"
           class="flex h-full w-full max-w-md flex-col gap-5 overflow-y-auto bg-paper p-5 shadow-2xl sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <h2 id="panel-title" class="font-display-narrow text-3xl">
                @if ($eventId !== null)
                    {{ $eventId ? __('Edit entry') : __('New entry') }}
                @else
                    {{ $selectedEntry?->title ?? __('Not found') }}
                @endif
            </h2>
            <button type="button" wire:click="closePanel" aria-label="{{ __('Close') }}" class="flex size-10 flex-none items-center justify-center rounded-full hover:bg-white">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        @if ($eventId !== null)
            {{-- Standalone entry --}}
            <form wire:submit="saveEvent" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <label for="ev-title" class="label-caps">{{ __('Title') }}</label>
                    <input id="ev-title" wire:model="event.title" class="{{ $field }}" placeholder="{{ __('e.g. Day off, Sick leave, Team meeting') }}" required maxlength="150">
                    @error('event.title') <span class="text-sm text-signal">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <label for="ev-from" class="label-caps">{{ __('From') }}</label>
                        <input id="ev-from" type="date" wire:model="event.starts_on" class="{{ $field }} min-w-0">
                        @error('event.starts_on') <span class="text-sm text-signal">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex min-w-0 flex-col gap-1">
                        <label for="ev-until" class="label-caps">{{ __('Until') }}</label>
                        <input id="ev-until" type="date" wire:model="event.ends_on" class="{{ $field }} min-w-0">
                        @error('event.ends_on') <span class="text-sm text-signal">{{ $message }}</span> @enderror
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="event.all_day" class="size-5 accent-ink">
                    {{ __('All day') }}
                </label>
                @unless ($event['all_day'] ?? false)
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <label for="ev-start" class="label-caps">{{ __('Start') }}</label>
                            <input id="ev-start" type="time" wire:model="event.starts_at" class="{{ $field }} min-w-0">
                            @error('event.starts_at') <span class="text-sm text-signal">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex min-w-0 flex-col gap-1">
                            <label for="ev-end" class="label-caps">{{ __('End') }}</label>
                            <input id="ev-end" type="time" wire:model="event.ends_at" class="{{ $field }} min-w-0">
                            @error('event.ends_at') <span class="text-sm text-signal">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endunless
                <div class="flex flex-col gap-1">
                    <label for="ev-instructor" class="label-caps">{{ __('Instructor (optional)') }}</label>
                    <select id="ev-instructor" wire:model="event.instructor_id" class="{{ $field }}">
                        <option value="">{{ __('— nobody in particular —') }}</option>
                        @foreach ($instructors as $i)
                            <option value="{{ $i->id }}">{{ $i->fullName() }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-muted">{{ __('An instructor with an entry cannot be assigned to courses at that time.') }}</span>
                    @error('event.instructor_id') <span role="alert" class="text-sm text-signal">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1">
                    <label for="ev-notes" class="label-caps">{{ __('Notes') }}</label>
                    <textarea id="ev-notes" wire:model="event.notes" rows="3" maxlength="2000" class="rounded-xl border border-line bg-white px-3 py-2 text-sm"></textarea>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                    <x-ui.button type="button" variant="ghost" wire:click="closePanel">{{ __('Cancel') }}</x-ui.button>
                    @if ($eventId)
                        <x-ui.button type="button" variant="danger" class="ml-auto" wire:click="deleteEvent" wire:confirm="{{ __('Delete this entry?') }}">{{ __('Delete') }}</x-ui.button>
                    @endif
                </div>
            </form>
        @elseif (! $selectedEntry)
            <p class="text-sm text-muted">{{ __('This entry no longer exists (e.g. the booking was cancelled).') }}</p>
        @else
            {{-- Course entry, read live from Grouping --}}
            @php
                $e = $selectedEntry;
            @endphp
            <dl class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-2 rounded-xl bg-white p-4 text-sm">
                <dt class="text-muted">{{ __('Date') }}</dt>
                <dd>{{ $e->date->isoFormat('dddd, D. MMMM YYYY') }}</dd>
                <dt class="text-muted">{{ __('Time') }}</dt>
                <dd class="tabular-nums">{{ $e->from }}–{{ $e->to }}</dd>
                <dt class="text-muted">{{ $e->kind === 'private' ? __('Students') : __('Group') }}</dt>
                <dd>{{ $e->detail }}</dd>
            </dl>

            @if ($e->kind === 'ungrouped')
                <p class="text-sm">{{ __('These students are not in a group yet. Form the groups first, then assign instructors.') }}</p>
            @else
                @if ($pending)
                    <div role="alert" class="flex flex-col gap-3 rounded-xl bg-white p-4 text-sm ring-2 ring-ink">
                        @foreach ($pending['warnings'] as $w)
                            <p>{{ $w }}</p>
                        @endforeach
                        <div class="flex gap-2">
                            <x-ui.button type="button" class="h-9" wire:click="confirmPending">{{ __('Assign anyway') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" class="h-9" wire:click="cancelPending">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </div>
                @endif
                @error('instructor')
                    <div role="alert" class="rounded-xl bg-white px-4 py-3 text-sm text-signal ring-2 ring-signal">{{ $message }}</div>
                @enderror
                <div class="flex flex-col gap-1">
                    <label for="panel-instructor" class="label-caps">{{ __('Instructor') }}</label>
                    <select id="panel-instructor" wire:change="assign($event.target.value)" wire:key="pi-{{ $e->key() }}-{{ $e->instructorId }}-{{ $redraw }}" class="{{ $field }} {{ $e->instructorId ? '' : 'border-signal' }}">
                        <option value="">{{ __('— none —') }}</option>
                        @foreach ($instructors as $i)
                            @continue(! $i->is_active && $i->id !== $e->instructorId)
                            <option value="{{ $i->id }}" @selected($i->id === $e->instructorId)>{{ $i->fullName() }}@if ($e->sportId && ! $i->sports->contains('id', $e->sportId)) ({{ __('other sport') }})@endif</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-muted">{{ __('Changes here are the same as in Grouping.') }}</span>
                </div>
            @endif

            <x-ui.button variant="secondary" :href="route('grouping', ['date' => $e->date->toDateString()])">{{ __('Open in Grouping') }}</x-ui.button>
        @endif
    </aside>
</div>
