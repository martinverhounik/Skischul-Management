@php
    $quote = $this->quote;
    $chip = fn (bool $on) => $on ? 'bg-ink text-white ring-ink' : 'bg-white text-ink ring-line hover:bg-paper';
    $field = 'h-11 w-full rounded-lg border border-line bg-white px-3 text-sm';
    $err = fn (string $key) => $quote?->errors[$key] ?? null;
@endphp
<div class="flex flex-wrap items-start gap-5">
    <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-5">

        @if ($status)
            <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
        @endif

        @if ($editedBooking)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm ring-2 ring-signal">
                <span>{{ __('You are changing booking :number (:name). Nothing is saved until you confirm at the end.', ['number' => $editedBooking->number, 'name' => $editedBooking->booker_name]) }}</span>
                <a href="{{ route('bookings.show', $editedBooking) }}" wire:navigate class="underline hover:text-signal">{{ __('Stop changing') }}</a>
            </div>
        @endif

        @if (! $school->hasSeason())
            <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-4 text-sm">
                <span class="font-semibold text-signal">{{ __('No season is set yet.') }}</span>
                {{ __('Courses can only be booked inside the season.') }}
                @can('admin')<a href="{{ route('settings.season') }}" class="underline">{{ __('Set the season') }}</a>@endcan
            </div>
        @endif

        @if ($table && $configuring)
            {{-- ===================== Configurator ===================== --}}
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <span class="label-caps">{{ $editingKey ? __('Change course in cart') : $table->course_type->label() }}</span>
                        <h2 class="font-display-narrow text-3xl">{{ $table->displayName() }}</h2>
                        @if ($table->description)<p class="max-w-prose text-sm text-muted">{{ $table->description }}</p>@endif
                    </div>
                    <x-ui.button type="button" variant="ghost" wire:click="closeConfigurator">{{ __('Back to the courses') }}</x-ui.button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @if ($table->sports->count() > 1)
                        <div class="flex flex-col gap-1.5">
                            <label for="cfg-sport" class="label-caps">{{ __('Sport') }} *</label>
                            <select id="cfg-sport" wire:model.live="item.sport_id" class="{{ $field }}">
                                @foreach ($table->sports as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex flex-col gap-1.5">
                        <label for="cfg-date" class="label-caps">{{ $table->course_type->isHourly() ? __('Date') : __('Start date') }} *</label>
                        <select id="cfg-date" wire:model.live="item.start_date" class="{{ $field }} {{ $err('start_date') ? 'border-signal' : '' }}">
                            @if ($dateOptions->isEmpty())<option value="">{{ __('No date available in this period') }}</option>@endif
                            @foreach ($dateOptions as $d)
                                <option value="{{ $d->toDateString() }}">{{ $d->isoFormat('dd, L') }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-muted">
                            {{ $table->course_type->isHourly() ? __('Only days the school is open.') : __('Only allowed start days in the season, never closed days.') }}
                            {{ __('Period: :from – :to', ['from' => $windowFrom->isoFormat('L'), 'to' => $windowTo->isoFormat('L')]) }}
                        </p>
                        @if ($err('start_date'))<p class="text-sm text-signal">{{ $err('start_date') }}</p>@endif
                    </div>

                    @if ($table->course_type->isHourly())
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="cfg-time" class="label-caps">{{ __('Start time') }} *</label>
                                <input id="cfg-time" type="time" step="900" wire:model.live.debounce.400ms="item.start_time" class="{{ $field }} {{ $err('start_time') ? 'border-signal' : '' }}">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="cfg-hours" class="label-caps">{{ __('Hours') }} *</label>
                                <select id="cfg-hours" wire:model.live="item.hours" class="{{ $field }} {{ $err('hours') ? 'border-signal' : '' }}">
                                    @foreach (range(1, (int) $table->max_hours) as $h)<option value="{{ $h }}">{{ trans_choice(':count hour|:count hours', $h) }}</option>@endforeach
                                </select>
                            </div>
                            @if ($err('start_time'))<p class="col-span-2 text-sm text-signal">{{ $err('start_time') }}</p>@endif
                            @if ($err('hours'))<p class="col-span-2 text-sm text-signal">{{ $err('hours') }}</p>@endif
                            @if ($quote?->option && $quote->endTime)
                                <p class="col-span-2 text-xs text-muted">{{ $quote->startTime }}–{{ $quote->endTime }} · {{ $quote->option->label() }}</p>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-col gap-1.5">
                            <span class="label-caps">{{ __('Time') }} *</span>
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Time') }}">
                                @foreach ($table->activeTimeslotKeys() as $key)
                                    @php
                                        $slot = $school->timeslot($key);
                                    @endphp
                                    <label wire:key="slot-{{ $key->value }}" class="flex cursor-pointer flex-col rounded-xl px-4 py-2 text-sm ring-1 {{ $chip(($item['timeslot_key'] ?? '') === $key->value) }}">
                                        <input type="radio" value="{{ $key->value }}" wire:model.live="item.timeslot_key" class="sr-only">
                                        <span class="font-semibold">{{ $key->label() }}</span>
                                        @if ($slot)<span class="text-xs tabular-nums opacity-75">{{ $slot->startsAtLabel() }}–{{ $slot->endsAtLabel() }}</span>@endif
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="cfg-days" class="label-caps">{{ __('Number of days') }} *</label>
                            <select id="cfg-days" wire:model.live="item.days" class="{{ $field }}">
                                @foreach (range($table->min_days, $table->max_days) as $d)<option value="{{ $d }}">{{ trans_choice(':count day|:count days', $d) }}</option>@endforeach
                            </select>
                            @if ($err('days'))<p class="text-sm text-signal">{{ $err('days') }}</p>@endif
                        </div>
                    @endif
                </div>

                @if ($quote && $quote->lessonDates->isNotEmpty() && ! $table->course_type->isHourly())
                    <div class="flex flex-col gap-2 rounded-xl bg-white p-4">
                        <span class="label-caps">{{ __('Lesson days') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($quote->lessonDates as $d)
                                <span class="rounded-full bg-paper px-3 py-1 text-sm tabular-nums">{{ $d->isoFormat('dd D.M.') }}</span>
                            @endforeach
                        </div>
                        @if ($quote->hasFewerLessons())
                            <p class="text-sm">{{ __('Because of a closed day this week has only :count lesson days. Only these are charged.', ['count' => $quote->lessonDates->count()]) }}</p>
                        @endif
                    </div>
                @endif

                {{-- Participants --}}
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="label-caps">{{ __('Participants') }} *</span>
                        <span class="text-xs text-muted">
                            {{ __('For :ages · :levels', ['ages' => $table->ageGroups->map(fn ($g) => "{$g->name} {$g->min_age}–{$g->max_age}")->implode(', '), 'levels' => $table->skillLevels->pluck('name')->implode(', ')]) }}
                        </span>
                    </div>

                    @if (($item['students'] ?? []) !== [])
                        <ul class="flex flex-col divide-y divide-hairline rounded-xl bg-white">
                            @foreach ($item['students'] as $chosen)
                                @php
                                    $s = $chosenStudents->get($chosen['id']);
                                    $levelError = $quote?->errors['students.'.$chosen['id']] ?? null;
                                    $ageWarning = $quote?->ageWarnings[$chosen['id']] ?? null;
                                @endphp
                                @continue(! $s)
                                <li wire:key="chosen-{{ $s->id }}" class="flex flex-col gap-2 px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="flex items-center gap-3">
                                            <span aria-hidden="true" class="flex size-9 items-center justify-center rounded-full bg-sand text-xs font-semibold">{{ $s->initials() }}</span>
                                            <span class="flex flex-col">
                                                <span class="font-semibold">{{ $s->fullName() }}</span>
                                                <span class="flex items-center gap-1.5 text-xs text-muted">
                                                    <span aria-hidden="true" class="size-2 rounded-full" style="background: {{ $s->skillLevel?->color ?? '#d9d7d0' }}"></span>
                                                    {{ $s->skillLevel?->name }} ·
                                                    {{ trans_choice(':count year|:count years', $quote?->participants[$s->id]['age'] ?? $s->ageOn($windowFrom)) }}
                                                    @if ($chosen['age_override']) · <span class="text-ink">{{ __('age confirmed') }}</span>@endif
                                                </span>
                                            </span>
                                        </span>
                                        <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="removeStudent({{ $s->id }})" aria-label="{{ __('Remove :name', ['name' => $s->fullName()]) }}">{{ __('Remove') }}</x-ui.button>
                                    </div>
                                    @if ($levelError)
                                        <p class="text-sm text-signal">{{ $levelError }}</p>
                                    @elseif ($ageWarning)
                                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-paper px-3 py-2 text-sm">
                                            <span>{{ $ageWarning }}</span>
                                            <x-ui.button type="button" variant="secondary" class="h-9 px-3" wire:click="allowAge({{ $s->id }})">{{ __('Book anyway') }}</x-ui.button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="relative">
                        <label for="student-search" class="sr-only">{{ __('Find a student') }}</label>
                        <input id="student-search" type="search" wire:model.live.debounce.250ms="studentSearch" autocomplete="off"
                               placeholder="{{ __('Find a student by name or phone…') }}" class="h-11 w-full rounded-full border border-line bg-white px-4 text-sm">
                        @if ($this->studentResults->isNotEmpty())
                            <ul class="absolute inset-x-0 top-12 z-20 flex flex-col overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-line">
                                @foreach ($this->studentResults as $r)
                                    <li wire:key="result-{{ $r->id }}">
                                        <button type="button" wire:click="addStudent({{ $r->id }})" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm hover:bg-paper">
                                            <span><span class="font-semibold">{{ $r->fullName() }}</span> <span class="text-muted">· {{ $r->date_of_birth->isoFormat('L') }} · {{ $r->skillLevel?->name }}</span></span>
                                            <span class="text-muted">{{ __('Add') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (mb_strlen(trim($studentSearch)) >= 2)
                            <p class="mt-2 text-sm text-muted">{{ __('No student found.') }}</p>
                        @endif
                    </div>

                    @if ($showNewStudent)
                        <form wire:submit="createStudent" class="flex flex-col gap-4 rounded-xl bg-white p-4">
                            <span class="font-semibold">{{ __('New student') }}</span>
                            @include('livewire.students.partials.fields', ['prefix' => 'newStudent', 'levels' => $levelOptions])
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="ghost" wire:click="$set('showNewStudent', false)">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button>{{ __('Create and add') }}</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div><x-ui.button type="button" variant="secondary" wire:click="$set('showNewStudent', true)">{{ __('New student') }}</x-ui.button></div>
                    @endif

                    @if ($err('students'))<p class="text-sm text-signal">{{ $err('students') }}</p>@endif
                    <p class="text-xs text-muted">{{ __('Planned group size :min–:max. This never blocks a booking.', ['min' => $table->min_participants, 'max' => $table->max_participants]) }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-full bg-ink p-2 pl-6 text-white">
                    <span class="text-sm" aria-live="polite">
                        @if ($quote && $quote->isValid())
                            <span class="font-display-narrow text-2xl tabular-nums">{{ App\Support\Money::format($quote->priceCents) }}</span>
                            <span class="text-white/70">· {{ trans_choice(':count participant|:count participants', count($quote->participants)) }}</span>
                        @elseif ($quote && ($quote->errors['price'] ?? null))
                            {{ $quote->errors['price'] }}
                        @else
                            <span class="text-white/70">{{ __('Complete the details to see the price') }}</span>
                        @endif
                    </span>
                    <x-ui.button type="button" variant="secondary" wire:click="addToCart" :disabled="! ($quote && $quote->isValid())">
                        {{ $editingKey ? __('Save changes') : __('Add to cart') }}
                    </x-ui.button>
                </div>
            </x-ui.card>
        @else
            {{-- ===================== Filters + course list (US-09) ===================== --}}
            <x-ui.card :title="__('Book a course')">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="f-from" class="label-caps">{{ __('Travel from') }}</label>
                        <input id="f-from" type="date" wire:model.live="from" class="{{ $field }}">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="f-to" class="label-caps">{{ __('Travel until') }}</label>
                        <input id="f-to" type="date" wire:model.live="to" min="{{ $windowFrom->toDateString() }}" class="{{ $field }}">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="f-type" class="label-caps">{{ __('Course type') }}</label>
                        <select id="f-type" wire:model.live="type" class="{{ $field }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach (App\Enums\CourseType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="f-sport" class="label-caps">{{ __('Sport') }}</label>
                        <select id="f-sport" wire:model.live="sport" class="{{ $field }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($sports as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="f-age" class="label-caps">{{ __('Age group') }}</label>
                        <select id="f-age" wire:model.live="age" class="{{ $field }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($ageGroups as $a)<option value="{{ $a->id }}">{{ $a->name }} ({{ $a->min_age }}–{{ $a->max_age }})</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="f-level" class="label-caps">{{ __('Skill level') }}</label>
                        <select id="f-level" wire:model.live="level" class="{{ $field }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($levels as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted">
                    <span>{{ __('Showing courses available :from – :to', ['from' => $windowFrom->isoFormat('L'), 'to' => $windowTo->isoFormat('L')]) }}</span>
                    <button type="button" wire:click="resetFilters" class="underline hover:text-signal">{{ __('Reset filters') }}</button>
                </div>

                @if ($this->courses->isEmpty())
                    <div class="rounded-xl bg-white p-6 text-sm text-muted">{{ __('No course matches these filters in this period.') }}</div>
                @else
                    <ul class="grid gap-3 lg:grid-cols-2">
                        @foreach ($this->courses as $course)
                            @php($hourly = $course->course_type->isHourly())
                            <li wire:key="course-{{ $course->id }}" class="flex flex-col gap-3 rounded-xl bg-white p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $hourly ? 'bg-ink text-white' : 'ring-1 ring-ink' }}">{{ $course->course_type->label() }}</span>
                                    <span class="font-semibold">{{ $course->displayName() }}</span>
                                </div>
                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                                    <dt class="label-caps pt-0.5">{{ __('For') }}</dt>
                                    <dd>{{ $course->ageGroups->pluck('name')->implode(', ') }} · {{ $course->skillLevels->pluck('name')->implode(', ') }}</dd>
                                    <dt class="label-caps pt-0.5">{{ __('When') }}</dt>
                                    <dd>{{ collect($course->activeTimeslotKeys())->map->label()->implode(', ') }}
                                        @unless ($hourly) · {{ __('starts') }} {{ collect($course->start_weekdays)->map(fn ($d) => now()->startOfWeek()->addDays(($d + 6) % 7)->isoFormat('dd'))->implode(', ') }} @endunless
                                    </dd>
                                    <dt class="label-caps pt-0.5">{{ __('Price') }}</dt>
                                    <dd class="tabular-nums">{{ __('from :price', ['price' => App\Support\Money::format($course->prices->min('price_cents') ?? 0)]) }}</dd>
                                </dl>
                                <div class="mt-auto flex justify-end">
                                    <x-ui.button type="button" wire:click="configure({{ $course->id }})">{{ __('Choose') }}</x-ui.button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif
    </div>

    {{-- ===================== Cart (US-12) ===================== --}}
    <aside aria-label="{{ __('Cart') }}" class="flex w-full flex-col gap-4 rounded-[var(--radius-card)] bg-ink p-5 text-white lg:sticky lg:top-4 lg:w-[360px] lg:flex-none">
        <div class="flex items-baseline justify-between">
            <h2 class="font-display-narrow text-3xl">{{ __('Cart') }}</h2>
            <span class="label-caps !text-white/60">{{ trans_choice(':count course|:count courses', count($cartQuotes)) }}</span>
        </div>

        @if ($cartQuotes === [])
            <p class="text-sm text-white/70">{{ __('No courses yet. Choose a course on the left.') }}</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($cartQuotes as $key => $q)
                    <li wire:key="cart-{{ $key }}" class="flex flex-col gap-1.5 rounded-xl bg-ink-soft p-3 {{ $editingKey === $key ? 'ring-2 ring-white' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-semibold">{{ $q->priceTable?->displayName() ?? __('Unknown course') }}</span>
                            <span class="tabular-nums">{{ $q->isValid() ? App\Support\Money::format($q->priceCents) : '–' }}</span>
                        </div>
                        @if ($q->startDate())
                            <span class="text-xs text-white/70">
                                @if ($q->priceTable->course_type->isHourly())
                                    {{ $q->startDate()->isoFormat('dd, L') }} · {{ $q->startTime }}–{{ $q->endTime }}
                                @else
                                    {{ $q->startDate()->isoFormat('dd D.M.') }} – {{ $q->endDate()->isoFormat('dd D.M.') }} · {{ $q->option?->label() }} · {{ trans_choice(':count day|:count days', $q->lessonDates->count()) }}
                                @endif
                            </span>
                        @endif
                        <span class="text-xs text-white/70">{{ collect($q->participants)->map(fn ($p) => $p['student']->fullName())->implode(', ') }}</span>
                        @unless ($q->isValid())
                            <span class="text-xs font-semibold text-white">{{ collect($q->errors)->first() ?? collect($q->ageWarnings)->first() }}</span>
                        @endunless
                        <div class="flex flex-wrap justify-end gap-1">
                            @if ($q->priceTable?->course_type->isHourly())
                                <button type="button" wire:click="addAnotherLesson('{{ $key }}')" class="rounded-full px-3 py-1.5 text-xs hover:bg-white/10">{{ __('+ another lesson') }}</button>
                            @endif
                            <button type="button" wire:click="editCartItem('{{ $key }}')" class="rounded-full px-3 py-1.5 text-xs hover:bg-white/10">{{ __('Edit') }}</button>
                            <button type="button" wire:click="removeFromCart('{{ $key }}')" class="rounded-full px-3 py-1.5 text-xs hover:bg-white/10">{{ __('Remove') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($addOns->isNotEmpty())
            <div class="flex flex-col gap-2 border-t border-white/15 pt-4">
                <span class="label-caps !text-white/60">{{ __('Add-ons') }}</span>
                @foreach ($addOns as $addOn)
                    @php($qty = $cartAddOns[$addOn->id] ?? 0)
                    <div wire:key="addon-{{ $addOn->id }}" class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate">{{ $addOn->name }}</span>
                            <span class="text-xs tabular-nums text-white/60">{{ App\Support\Money::format($addOn->price_cents) }}</span>
                        </span>
                        <span class="flex flex-none items-center gap-1">
                            <button type="button" wire:click="setAddOn({{ $addOn->id }}, {{ $qty - 1 }})" @disabled($qty === 0) aria-label="{{ __('One less :name', ['name' => $addOn->name]) }}" class="flex size-9 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-30">−</button>
                            <span class="w-6 text-center tabular-nums" aria-label="{{ __('Quantity') }}">{{ $qty }}</span>
                            <button type="button" wire:click="setAddOn({{ $addOn->id }}, {{ $qty + 1 }})" aria-label="{{ __('One more :name', ['name' => $addOn->name]) }}" class="flex size-9 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">+</button>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-baseline justify-between border-t border-white/15 pt-4">
            <span class="label-caps !text-white/60">{{ __('Subtotal') }}</span>
            <span class="font-display-narrow text-3xl tabular-nums">{{ App\Support\Money::format($subtotal) }}</span>
        </div>

        <a href="{{ route('booking.checkout', $editBooking ? ['booking' => $editBooking] : []) }}" wire:navigate
           class="flex h-12 items-center justify-center rounded-full bg-white font-semibold text-ink hover:bg-sand {{ $cartQuotes === [] ? 'pointer-events-none opacity-40' : '' }}"
           @if ($cartQuotes === []) aria-disabled="true" tabindex="-1" @endif>
            {{ $editBooking ? __('Continue to save changes') : __('Go to checkout') }}
        </a>
    </aside>
</div>
