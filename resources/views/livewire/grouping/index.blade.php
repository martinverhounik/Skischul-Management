@php
    $slot = fn ($key) => $school->timeslot($key);
    $field = 'h-9 rounded-lg border border-line bg-white px-2 text-sm';
@endphp
<div class="flex flex-col gap-5">
    {{-- Time navigation (US-23) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-card)] bg-paper p-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="previous" aria-label="{{ $view === 'week' ? __('Previous week') : __('Previous day') }}" class="flex size-11 items-center justify-center rounded-full bg-white hover:bg-sand">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <div class="flex min-w-0 flex-1 flex-col items-center sm:min-w-[220px] sm:flex-none">
                <span class="font-display-narrow text-2xl">
                    @if ($view === 'week')
                        {{ $weekStart->isoFormat('D. MMM') }} – {{ $weekStart->addDays(6)->isoFormat('D. MMM YYYY') }}
                    @else
                        {{ $day->isoFormat('dddd, D. MMMM') }}
                    @endif
                </span>
                <label class="sr-only" for="pick-date">{{ __('Date') }}</label>
                <input id="pick-date" type="date" wire:model.live="date" class="h-8 rounded-full border-0 bg-transparent text-center text-xs text-muted">
            </div>
            <button type="button" wire:click="next" aria-label="{{ $view === 'week' ? __('Next week') : __('Next day') }}" class="flex size-11 items-center justify-center rounded-full bg-white hover:bg-sand">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <button type="button" wire:click="today" class="h-9 rounded-full px-4 text-sm hover:bg-white">{{ __('Today') }}</button>
        </div>
        <div class="flex rounded-full bg-white p-1" role="group" aria-label="{{ __('View') }}">
            @foreach (['day' => __('Day'), 'week' => __('Week')] as $v => $label)
                <button type="button" wire:click="$set('view', '{{ $v }}')" aria-pressed="{{ $view === $v ? 'true' : 'false' }}"
                        class="h-9 rounded-full px-5 text-sm font-medium {{ $view === $v ? 'bg-ink text-white' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($status)
        <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
    @endif
    @error('instructor')
        <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal ring-2 ring-signal">{{ $message }}</div>
    @enderror
    @error('group')
        <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal">{{ $message }}</div>
    @enderror
    @if ($pending)
        <div role="alertdialog" aria-labelledby="pending-text" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm ring-2 ring-ink">
            <span id="pending-text">{{ implode(' ', $pending['warnings']) }}</span>
            <span class="flex gap-2">
                <x-ui.button type="button" variant="ghost" class="h-9 px-4" wire:click="cancelPending">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="button" class="h-9 px-4" wire:click="confirmPending">{{ __('Assign anyway') }}</x-ui.button>
            </span>
        </div>
    @endif

    @if ($view === 'week')
        {{-- ===================== Week (US-23) ===================== --}}
        <x-ui.card :title="__('Week overview')">
            <p class="text-sm text-muted">{{ __('Students per sport, age group and time. Click a day to plan it.') }}</p>
            <div class="relative overflow-x-auto rounded-xl bg-white">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-hairline">
                            <th scope="col" class="label-caps px-4 py-3 text-left font-normal">{{ __('Group courses') }}</th>
                            @foreach ($week as $d)
                                <th scope="col" class="px-2 py-2">
                                    <button type="button" wire:click="showDay('{{ $d['date']->toDateString() }}')" class="flex w-full flex-col items-center rounded-lg px-2 py-1 hover:bg-paper">
                                        <span class="label-caps">{{ $d['date']->isoFormat('dd') }}</span>
                                        <span class="font-display-narrow text-2xl">{{ $d['date']->day }}</span>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr wire:key="row-{{ $row->key() }}" class="border-b border-hairline">
                                <th scope="row" class="px-4 py-2.5 text-left font-normal">
                                    <span class="font-semibold">{{ $row->sport->name }} · {{ $row->ageGroup->name }}</span>
                                    <span class="block text-xs text-muted">{{ $row->timeslot->label() }}</span>
                                </th>
                                @foreach ($week as $d)
                                    @php
    $c = $d['cohorts']->get($row->key());
@endphp
                                    <td class="px-2 py-2 text-center">
                                        @if ($c && $c->studentCount() > 0)
                                            <button type="button" wire:click="showDay('{{ $d['date']->toDateString() }}')" class="flex w-full flex-col items-center rounded-lg py-1 hover:bg-paper">
                                                <span class="font-semibold tabular-nums">{{ $c->studentCount() }}</span>
                                                <span class="text-xs tabular-nums {{ $c->instructorsAssigned() < $c->instructorsNeeded() ? 'font-semibold text-signal' : 'text-muted' }}">
                                                    {{ $c->instructorsAssigned() }}/{{ $c->instructorsNeeded() }} {{ __('instr.') }}
                                                </span>
                                            </button>
                                        @else
                                            <span class="text-muted">–</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-6 text-center text-muted">{{ __('No group courses this week.') }}</td></tr>
                        @endforelse
                        <tr>
                            <th scope="row" class="px-4 py-2.5 text-left font-semibold">{{ __('Private lessons') }}</th>
                            @foreach ($week as $d)
                                @php
                                    $total = $d['private']->count();
                                    $done = $d['private']->whereNotNull('instructor_id')->count();
                                @endphp
                                <td class="px-2 py-2 text-center">
                                    @if ($total)
                                        <span class="font-semibold tabular-nums">{{ $total }}</span>
                                        <span class="block text-xs tabular-nums {{ $done < $total ? 'font-semibold text-signal' : 'text-muted' }}">{{ $done }}/{{ $total }} {{ __('assigned') }}</span>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-muted">{{ __('"2/3 instr." means 2 of the 3 instructors needed are assigned. Red = still missing.') }}</p>
        </x-ui.card>
    @else
        {{-- ===================== Day (US-22, US-24) ===================== --}}
        @php
            $students = $cohorts->sum(fn ($c) => $c->studentCount());
            $needed = $cohorts->sum(fn ($c) => $c->instructorsNeeded());
            $assigned = $cohorts->sum(fn ($c) => $c->instructorsAssigned());
        @endphp
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                [__('Students in groups'), $students, false],
                [__('Instructors needed'), $needed, false],
                [__('Assigned'), $assigned.' / '.$needed, $assigned < $needed],
                [__('Private lessons'), $private->whereNotNull('instructor_id')->count().' / '.$private->count(), $private->whereNull('instructor_id')->isNotEmpty()],
            ] as [$label, $value, $alert])
                <div class="flex flex-col gap-1 rounded-[var(--radius-card)] bg-paper p-4">
                    <span class="label-caps">{{ $label }}</span>
                    <span class="font-display-narrow text-4xl tabular-nums {{ $alert ? 'text-signal' : '' }}">{{ $value }}</span>
                </div>
            @endforeach
        </div>

        <x-ui.card :title="__('Group courses')">
            @if ($cohorts->isEmpty())
                <p class="rounded-xl bg-white p-6 text-sm text-muted">{{ __('No group course students on this day.') }}</p>
            @endif

            @foreach ($bySport as $sportCohorts)
                @php
    $sport = $sportCohorts->first()->sport;
@endphp
                <section aria-labelledby="sport-{{ $sport->id }}" class="flex flex-col gap-3">
                    <h3 id="sport-{{ $sport->id }}" class="flex items-baseline gap-2 text-lg font-semibold">
                        {{ $sport->name }}
                        <span class="text-sm font-normal text-muted">{{ trans_choice(':count student|:count students', $sportCohorts->sum(fn ($c) => $c->studentCount())) }}</span>
                    </h3>

                    @foreach ($sportCohorts as $cohort)
                        @php
                            $s = $slot($cohort->timeslot);
                            $short = $cohort->instructorsAssigned() < $cohort->instructorsNeeded();
                            $choices = $groupChoices->get($cohort->timeslot->value.'-'.$cohort->sport->id, collect());
                        @endphp
                        <div wire:key="cohort-{{ $cohort->key() }}" class="flex flex-col gap-3 rounded-xl bg-white p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex flex-col gap-1">
                                    <span class="font-semibold">{{ $cohort->ageGroup->name }} <span class="font-normal text-muted">({{ $cohort->ageGroup->min_age }}–{{ $cohort->ageGroup->max_age }})</span> · {{ $cohort->timeslot->label() }}@if ($s) <span class="font-normal tabular-nums text-muted">{{ $s->startsAtLabel() }}–{{ $s->endsAtLabel() }}</span>@endif</span>
                                    <span class="text-sm">
                                        {{ trans_choice(':count student|:count students', $cohort->studentCount()) }} ·
                                        <span class="{{ $short ? 'font-semibold text-signal' : '' }}">{{ __(':assigned of :needed instructors', ['assigned' => $cohort->instructorsAssigned(), 'needed' => $cohort->instructorsNeeded()]) }}</span>
                                        <span class="text-muted">({{ __('max. :n per group', ['n' => $cohort->maxParticipants]) }})</span>
                                    </span>
                                    <span class="flex flex-wrap gap-1.5 pt-1">
                                        @foreach ($cohort->levelCounts() as $level => $n)
                                            <span class="rounded-full bg-paper px-2.5 py-0.5 text-xs">{{ $level }} {{ $n }}</span>
                                        @endforeach
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    @if ($cohort->unassigned()->isNotEmpty())
                                        <x-ui.button type="button" class="h-9 px-4" wire:click="autoSplit('{{ $cohort->key() }}')">{{ __('Form groups') }}</x-ui.button>
                                        <x-ui.button type="button" variant="secondary" class="h-9 px-4 ring-1 ring-line" wire:click="copyPreviousDay('{{ $cohort->key() }}')">{{ __('Like the previous day') }}</x-ui.button>
                                    @endif
                                    <x-ui.button type="button" variant="ghost" class="h-9 px-4" wire:click="addGroup('{{ $cohort->key() }}')">{{ __('+ group') }}</x-ui.button>
                                </div>
                            </div>

                            <div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                                @foreach ($cohort->groups as $group)
                                    @php
                                        $members = $cohort->entriesOf($group);
                                    @endphp
                                    <div wire:key="group-{{ $group->id }}" class="flex min-w-0 flex-col gap-2 rounded-xl p-3 ring-1 {{ $group->instructor_id ? 'ring-line' : 'ring-signal' }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <label class="sr-only" for="gname-{{ $group->id }}">{{ __('Group name') }}</label>
                                            <input id="gname-{{ $group->id }}" value="{{ $group->name }}" wire:change="renameGroup({{ $group->id }}, $event.target.value)"
                                                   class="min-w-0 flex-1 rounded-md border-0 bg-transparent px-1 font-semibold hover:bg-paper focus:bg-paper">
                                            <span class="text-xs tabular-nums text-muted">{{ $members->count() }}/{{ $cohort->maxParticipants }}</span>
                                            <button type="button" wire:click="deleteGroup({{ $group->id }})" wire:confirm="{{ __('Delete :name? Its students will be without a group.', ['name' => $group->name]) }}"
                                                    aria-label="{{ __('Delete :name', ['name' => $group->name]) }}" class="flex size-8 items-center justify-center rounded-full text-muted hover:bg-paper hover:text-signal">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <div class="flex min-w-0 flex-col gap-1">
                                                <label for="gi-{{ $group->id }}" class="label-caps">{{ __('Instructor') }}</label>
                                                <select id="gi-{{ $group->id }}" wire:key="gi-{{ $group->id }}-{{ $redraw }}" wire:change="assignGroup({{ $group->id }}, $event.target.value)" class="{{ $field }} w-full min-w-0 {{ $group->instructor_id ? '' : 'border-signal' }}">
                                                    <option value="">{{ __('— none —') }}</option>
                                                    @foreach ($instructors as $i)
                                                        @continue(! $i->is_active && $i->id !== $group->instructor_id)
                                                        <option value="{{ $i->id }}" @selected($i->id === $group->instructor_id)>{{ $i->fullName() }}@unless ($i->sports->contains('id', $cohort->sport->id)) ({{ __('other sport') }})@endunless</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex min-w-0 flex-col gap-1">
                                                <label for="gm-{{ $group->id }}" class="label-caps">{{ __('Meeting point') }}</label>
                                                <select id="gm-{{ $group->id }}" wire:change="setGroupMeetingPoint({{ $group->id }}, $event.target.value)" class="{{ $field }} w-full min-w-0">
                                                    <option value="">–</option>
                                                    @foreach ($meetingPoints as $mp)<option value="{{ $mp->id }}" @selected($mp->id === $group->meeting_point_id)>{{ $mp->name }}</option>@endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <ul class="flex flex-col divide-y divide-hairline">
                                            @forelse ($members as $entry)
                                                @include('livewire.grouping.partials.entry', ['entry' => $entry, 'current' => $group->id])
                                            @empty
                                                <li class="py-2 text-xs text-muted">{{ __('No students yet.') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>

                            @if ($cohort->unassigned()->isNotEmpty())
                                <div class="flex flex-col gap-2 rounded-xl bg-paper p-3">
                                    <span class="label-caps">{{ __('Without group') }} ({{ $cohort->unassigned()->count() }})</span>
                                    <ul class="grid gap-x-6 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($cohort->unassigned() as $entry)
                                            @include('livewire.grouping.partials.entry', ['entry' => $entry, 'current' => null])
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach
        </x-ui.card>

        {{-- Private lessons, listed by time (US-22) --}}
        <x-ui.card :title="__('Private lessons')">
            @if ($private->isEmpty())
                <p class="rounded-xl bg-white p-6 text-sm text-muted">{{ __('No private lessons on this day.') }}</p>
            @else
                <div class="relative overflow-x-auto rounded-xl bg-white">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="border-b border-hairline text-left">
                                <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Time') }}</th>
                                <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Course') }}</th>
                                <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Students') }}</th>
                                <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Instructor') }}</th>
                                <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Meeting point') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($private as $lesson)
                                <tr wire:key="private-{{ $lesson->id }}" class="border-b border-hairline last:border-0">
                                    <td class="px-4 py-2.5 font-semibold tabular-nums">{{ substr($lesson->starts_at, 0, 5) }}–{{ substr($lesson->ends_at, 0, 5) }}</td>
                                    <td class="px-3 py-2.5">
                                        {{ $lesson->line->priceTable->displayName() }}
                                        <span class="block text-xs text-muted">{{ $lesson->line->sport->name }} · <a href="{{ route('bookings.show', $lesson->line->booking_id) }}" wire:navigate class="hover:text-signal">{{ $lesson->line->booking->number }}</a></span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @foreach ($lesson->line->students as $st)
                                            <span class="block">{{ $st->fullName() }} <span class="text-xs text-muted">({{ $st->pivot->age_at_start }}, {{ $st->skillLevel?->name }})</span></span>
                                        @endforeach
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <label class="sr-only" for="pi-{{ $lesson->id }}">{{ __('Instructor') }}</label>
                                        <select id="pi-{{ $lesson->id }}" wire:key="pi-{{ $lesson->id }}-{{ $redraw }}" wire:change="assignPrivate({{ $lesson->id }}, $event.target.value)" class="{{ $field }} w-full {{ $lesson->instructor_id ? '' : 'border-signal' }}">
                                            <option value="">{{ __('— none —') }}</option>
                                            @foreach ($instructors as $i)
                                                @continue(! $i->is_active && $i->id !== $lesson->instructor_id)
                                                <option value="{{ $i->id }}" @selected($i->id === $lesson->instructor_id)>{{ $i->fullName() }}@unless ($i->sports->contains('id', $lesson->line->sport_id)) ({{ __('other sport') }})@endunless</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <label class="sr-only" for="pm-{{ $lesson->id }}">{{ __('Meeting point') }}</label>
                                        <select id="pm-{{ $lesson->id }}" wire:change="setPrivateMeetingPoint({{ $lesson->id }}, $event.target.value)" class="{{ $field }} w-full">
                                            <option value="">–</option>
                                            @foreach ($meetingPoints as $mp)<option value="{{ $mp->id }}" @selected($mp->id === $lesson->meeting_point_id)>{{ $mp->name }}</option>@endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
