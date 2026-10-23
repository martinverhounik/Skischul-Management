@php
    $instructor = $form->instructor;
    $chip = fn (bool $on) => $on ? 'bg-ink text-white ring-ink' : 'bg-white text-ink ring-line hover:bg-paper';
@endphp
<div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
    <div class="flex flex-col gap-1 px-1">
        <a href="{{ route('instructors.index') }}" wire:navigate class="label-caps hover:text-signal">← {{ __('Instructors') }}</a>
        <h1 class="font-display-narrow text-4xl">{{ $instructor?->exists ? $instructor->fullName() : __('Add instructor') }}</h1>
    </div>

    <form wire:submit="save" class="flex flex-col gap-5">
        <x-ui.card :title="__('Person')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input :label="__('First name')" name="form.first_name" id="first-name" wire:model="form.first_name" required />
                <x-ui.input :label="__('Last name')" name="form.last_name" id="last-name" wire:model="form.last_name" required />
                <x-ui.input :label="__('Date of birth')" name="form.date_of_birth" id="dob" type="date" wire:model="form.date_of_birth" required />
                <x-ui.input :label="__('Certification')" name="form.certification_level" id="certification" wire:model="form.certification_level" :placeholder="__('e.g. Landesskilehrer')" :hint="__('Optional')" />
                <x-ui.input :label="__('Email')" name="form.email" id="email" type="email" wire:model="form.email" required />
                <x-ui.input :label="__('Phone')" name="form.phone" id="phone" type="tel" wire:model="form.phone" required />
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="label-caps mb-2">{{ __('Languages') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($languages as $code)
                        @php($on = in_array($code, $form->languages, true))
                        <label wire:key="lang-{{ $code }}" class="flex h-10 min-w-12 cursor-pointer items-center justify-center rounded-full px-3 text-sm font-medium ring-1 {{ $chip($on) }}">
                            <input type="checkbox" value="{{ $code }}" wire:model.live="form.languages" class="sr-only">{{ $code }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="flex flex-col gap-2">
                <legend class="label-caps mb-2">{{ __('Teaches') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sports as $sport)
                        @php($on = in_array((string) $sport->id, $form->sport_ids, true))
                        <label wire:key="sport-{{ $sport->id }}" class="flex h-10 cursor-pointer items-center rounded-full px-4 text-sm ring-1 {{ $chip($on) }}">
                            <input type="checkbox" value="{{ $sport->id }}" wire:model.live="form.sport_ids" class="sr-only">{{ $sport->name }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-muted">{{ __('Used to warn you when assigning a course in a sport the instructor does not teach.') }}</p>
            </fieldset>
        </x-ui.card>

        <x-ui.card :title="__('Payroll')">
            <p class="flex items-center gap-2 text-sm text-muted">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                {{ __('Stored encrypted. Only owners can see this.') }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input label="IBAN" name="form.iban" id="iban" wire:model.blur="form.iban" required autocomplete="off" placeholder="AT61 1904 3002 3457 3201" class="font-mono uppercase" />
                </div>
                <x-ui.input label="BIC / SWIFT" name="form.bic" id="bic" wire:model="form.bic" :hint="__('Optional')" class="font-mono uppercase" />
                <x-ui.input :label="__('Social security number')" name="form.social_security_number" id="ssn" wire:model="form.social_security_number" required autocomplete="off" />
                <x-ui.input :label="__('Hourly rate (€)')" name="form.hourly_rate" id="hourly-rate" inputmode="decimal" wire:model="form.hourly_rate" :hint="__('Optional')" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Notes & status')">
            <div class="flex flex-col gap-1.5">
                <label for="notes" class="label-caps">{{ __('Notes') }}</label>
                <textarea id="notes" wire:model="form.notes" rows="3" class="rounded-lg border border-line bg-white px-3 py-2 text-sm"></textarea>
            </div>
            <label class="flex items-start gap-3 rounded-xl bg-white p-4 text-sm">
                <input type="checkbox" wire:model="form.is_active" class="mt-0.5 size-4 accent-ink">
                <span class="flex flex-col gap-1">
                    <span class="font-semibold">{{ __('Active') }}</span>
                    <span class="text-muted">{{ __('Inactive instructors cannot be assigned and are hidden in the calendar filters.') }}</span>
                </span>
            </label>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <x-ui.button variant="ghost" :href="route('instructors.index')" wire:navigate>{{ __('Cancel') }}</x-ui.button>
            <x-ui.button>{{ $instructor?->exists ? __('Save') : __('Create instructor') }}</x-ui.button>
        </div>
    </form>
</div>
