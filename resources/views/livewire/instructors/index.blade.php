<div class="flex flex-col gap-5">
    <x-ui.card :title="__('Instructors')">
        @if ($isOwner)
            <x-slot:actions>
                <x-ui.button :href="route('instructors.create')" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
                    {{ __('Add instructor') }}
                </x-ui.button>
            </x-slot:actions>
        @endif

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex min-w-[240px] flex-1 flex-col gap-1.5">
                <label for="search" class="label-caps">{{ __('Search') }}</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or email') }}"
                       class="h-11 w-full rounded-full border border-line bg-white px-4 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="sport" class="label-caps">{{ __('Sport') }}</label>
                <select id="sport" wire:model.live="sport" class="h-11 rounded-full border border-line bg-white px-4 text-sm">
                    <option value="">{{ __('All sports') }}</option>
                    @foreach ($sports as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                </select>
            </div>
            <label class="flex h-11 items-center gap-2 rounded-full bg-white px-4 text-sm">
                <input type="checkbox" wire:model.live="showInactive" class="size-4 accent-ink">
                {{ __('Show inactive') }}
            </label>
        </div>

        @if ($instructors->isEmpty())
            <div class="rounded-xl bg-white p-6 text-sm text-muted">{{ __('No instructors found.') }}</div>
        @else
            <ul class="grid gap-3 md:grid-cols-2">
                @foreach ($instructors as $instructor)
                    <li wire:key="instructor-{{ $instructor->id }}"
                        class="flex flex-col gap-3 rounded-xl bg-white p-4 {{ $instructor->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-center gap-3">
                            <span aria-hidden="true" class="flex size-12 flex-none items-center justify-center rounded-full {{ $instructor->is_active ? 'bg-ink text-white' : 'bg-sand' }} font-semibold">{{ $instructor->initials() }}</span>
                            <span class="flex min-w-0 flex-1 flex-col">
                                <span class="truncate font-semibold">{{ $instructor->fullName() }}</span>
                                <span class="truncate text-sm text-muted">{{ $instructor->certification_level ?? '—' }}</span>
                            </span>
                            @unless ($instructor->is_active)
                                <span class="rounded-full bg-paper px-2 py-0.5 text-xs">{{ __('Inactive') }}</span>
                            @endunless
                        </div>
                        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                            <dt class="label-caps pt-0.5">{{ __('Teaches') }}</dt>
                            <dd>{{ $instructor->sports->pluck('name')->implode(', ') ?: '—' }}</dd>
                            <dt class="label-caps pt-0.5">{{ __('Languages') }}</dt>
                            <dd>{{ collect($instructor->languages)->implode(', ') ?: '—' }}</dd>
                            <dt class="label-caps pt-0.5">{{ __('Phone') }}</dt>
                            <dd class="tabular-nums"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $instructor->phone) }}" class="hover:text-signal">{{ $instructor->phone }}</a></dd>
                            <dt class="label-caps pt-0.5">{{ __('Email') }}</dt>
                            <dd class="truncate"><a href="mailto:{{ $instructor->email }}" class="hover:text-signal">{{ $instructor->email }}</a></dd>
                            @if ($isOwner)
                                <dt class="label-caps pt-0.5">IBAN</dt>
                                <dd class="font-mono text-xs leading-5">{{ App\Rules\Iban::mask($instructor->iban) }}</dd>
                                @if ($instructor->hourly_rate_cents !== null)
                                    <dt class="label-caps pt-0.5">{{ __('Rate') }}</dt>
                                    <dd class="tabular-nums">{{ App\Support\Money::format($instructor->hourly_rate_cents) }} / {{ __('hour') }}</dd>
                                @endif
                            @endif
                        </dl>
                        @if ($isOwner)
                            <div class="flex flex-wrap justify-end gap-1">
                                <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="toggleActive({{ $instructor->id }})">
                                    {{ $instructor->is_active ? __('Set inactive') : __('Set active') }}
                                </x-ui.button>
                                <x-ui.button variant="ghost" class="h-9 px-3" :href="route('instructors.edit', $instructor)" wire:navigate>{{ __('Edit') }}</x-ui.button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</div>
