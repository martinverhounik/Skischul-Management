{{--
    Student form fields (US-18). Reused wherever a student is created,
    e.g. inside the booking screen. $prefix is the Livewire form property.
--}}
@php($p = $prefix ?? 'form')
<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input :label="__('First name')" name="{{ $p }}.first_name" id="{{ $p }}-first-name" wire:model.blur="{{ $p }}.first_name" required autocomplete="off" />
    <x-ui.input :label="__('Last name')" name="{{ $p }}.last_name" id="{{ $p }}-last-name" wire:model.blur="{{ $p }}.last_name" required autocomplete="off" />
    <x-ui.input :label="__('Date of birth')" name="{{ $p }}.date_of_birth" id="{{ $p }}-dob" type="date" wire:model.blur="{{ $p }}.date_of_birth" required max="{{ now()->toDateString() }}" />
    <x-ui.select :label="__('Skill level')" name="{{ $p }}.skill_level_id" id="{{ $p }}-level" wire:model="{{ $p }}.skill_level_id" :options="['' => __('Please choose')] + $levels" required />

    <x-ui.input :label="__('Phone')" name="{{ $p }}.primary_phone" id="{{ $p }}-phone" type="tel" wire:model="{{ $p }}.primary_phone" required placeholder="+43 664 1234567" />
    <x-ui.input :label="__('Whose phone')" name="{{ $p }}.primary_phone_owner" id="{{ $p }}-phone-owner" wire:model="{{ $p }}.primary_phone_owner" required :placeholder="__('e.g. Mother, Father, self')" />
    <x-ui.input :label="__('Second phone')" name="{{ $p }}.secondary_phone" id="{{ $p }}-phone2" type="tel" wire:model="{{ $p }}.secondary_phone" :hint="__('Optional')" />
    <x-ui.input :label="__('Whose second phone')" name="{{ $p }}.secondary_phone_owner" id="{{ $p }}-phone2-owner" wire:model="{{ $p }}.secondary_phone_owner" />

    <x-ui.input :label="__('Language')" name="{{ $p }}.preferred_language" id="{{ $p }}-lang" wire:model="{{ $p }}.preferred_language" placeholder="DE, EN, NL …" list="{{ $p }}-lang-list" />
    <datalist id="{{ $p }}-lang-list">
        @foreach (['DE', 'EN', 'NL', 'FR', 'IT', 'CS', 'PL', 'HU', 'DA', 'SV'] as $code)<option value="{{ $code }}"></option>@endforeach
    </datalist>
    <x-ui.input :label="__('Accommodation')" name="{{ $p }}.accommodation" id="{{ $p }}-accommodation" wire:model="{{ $p }}.accommodation" :placeholder="__('e.g. Hotel Alpenhof')" />

    <div class="flex flex-col gap-1.5 sm:col-span-2">
        <label for="{{ $p }}-medical" class="label-caps">{{ __('Medical notes') }}</label>
        <textarea id="{{ $p }}-medical" wire:model="{{ $p }}.medical_notes" rows="2" placeholder="{{ __('Allergies, medication, anything instructors must know') }}" class="rounded-lg border border-line bg-white px-3 py-2 text-sm"></textarea>
        @error($p.'.medical_notes')<p class="text-sm text-signal">{{ $message }}</p>@enderror
    </div>
    <div class="flex flex-col gap-1.5 sm:col-span-2">
        <label for="{{ $p }}-notes" class="label-caps">{{ __('Notes') }}</label>
        <textarea id="{{ $p }}-notes" wire:model="{{ $p }}.notes" rows="2" class="rounded-lg border border-line bg-white px-3 py-2 text-sm"></textarea>
        @error($p.'.notes')<p class="text-sm text-signal">{{ $message }}</p>@enderror
    </div>
</div>
