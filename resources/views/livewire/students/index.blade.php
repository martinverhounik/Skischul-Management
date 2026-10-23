<div class="flex flex-col gap-5">
    <x-ui.card :title="__('Students')">
        <x-slot:actions>
            <x-ui.button :href="route('students.create')" wire:navigate>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
                {{ __('Add student') }}
            </x-ui.button>
        </x-slot:actions>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex min-w-[240px] flex-1 flex-col gap-1.5">
                <label for="search" class="label-caps">{{ __('Search') }}</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name, phone or accommodation') }}"
                           class="h-11 w-full rounded-full border border-line bg-white pl-9 pr-4 text-sm">
                </div>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="level" class="label-caps">{{ __('Skill level') }}</label>
                <select id="level" wire:model.live="level" class="h-11 rounded-full border border-line bg-white px-4 text-sm">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <label class="flex h-11 items-center gap-2 rounded-full bg-white px-4 text-sm">
                <input type="checkbox" wire:model.live="showArchived" class="size-4 accent-ink">
                {{ __('Show archived') }}
            </label>
        </div>

        <p class="text-sm text-muted" aria-live="polite">{{ trans_choice(':count student|:count students', $students->total()) }}</p>

        @if ($students->isEmpty())
            <div class="rounded-xl bg-white p-6 text-sm text-muted">
                {{ $search !== '' ? __('No student matches ":search".', ['search' => $search]) : __('No students yet.') }}
            </div>
        @else
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($students as $student)
                    <li wire:key="student-{{ $student->id }}">
                        <a href="{{ route('students.edit', $student) }}" wire:navigate
                           class="flex h-full flex-col gap-3 rounded-xl bg-white p-4 transition hover:ring-1 hover:ring-ink {{ $student->isArchived() ? 'opacity-60' : '' }}">
                            <div class="flex items-center gap-3">
                                <span aria-hidden="true" class="flex size-12 flex-none items-center justify-center rounded-full bg-sand font-semibold">{{ $student->initials() }}</span>
                                <span class="flex min-w-0 flex-col">
                                    <span class="truncate font-semibold">{{ $student->fullName() }}</span>
                                    <span class="text-sm text-muted">
                                        {{ trans_choice(':count year|:count years', $student->ageOn($today)) }} · {{ $student->date_of_birth->isoFormat('L') }}
                                    </span>
                                </span>
                                @if ($student->medical_notes)
                                    <span class="ml-auto flex-none rounded-full bg-signal px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white" title="{{ $student->medical_notes }}">{{ __('Medical') }}</span>
                                @endif
                            </div>
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                <dt class="label-caps pt-0.5">{{ __('Level') }}</dt>
                                <dd class="flex items-center gap-2">
                                    <span aria-hidden="true" class="size-2.5 rounded-full ring-1 ring-black/10" style="background: {{ $student->skillLevel?->color ?? '#d9d7d0' }}"></span>
                                    {{ $student->skillLevel?->name }}
                                </dd>
                                <dt class="label-caps pt-0.5">{{ __('Phone') }}</dt>
                                <dd class="tabular-nums">{{ $student->primary_phone }} <span class="text-muted">({{ $student->primary_phone_owner }})</span></dd>
                                @if ($student->accommodation)
                                    <dt class="label-caps pt-0.5">{{ __('Stays at') }}</dt>
                                    <dd class="truncate">{{ $student->accommodation }}</dd>
                                @endif
                                @if ($student->preferred_language)
                                    <dt class="label-caps pt-0.5">{{ __('Language') }}</dt>
                                    <dd>{{ $student->preferred_language }}</dd>
                                @endif
                            </dl>
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $students->links() }}
        @endif
    </x-ui.card>
</div>
