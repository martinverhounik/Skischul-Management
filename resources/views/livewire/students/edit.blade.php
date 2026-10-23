@php($student = $form->student)
<div class="mx-auto flex w-full max-w-3xl flex-col gap-5">
    <div class="flex flex-col gap-1 px-1">
        <a href="{{ route('students.index') }}" wire:navigate class="label-caps hover:text-signal">← {{ __('Students') }}</a>
        <h1 class="font-display-narrow text-4xl">{{ $student?->exists ? $student->fullName() : __('Add student') }}</h1>
        @if ($student?->isArchived())
            <p class="text-sm text-muted">{{ __('Archived on :date', ['date' => $student->archived_at->isoFormat('L')]) }}</p>
        @endif
    </div>

    @if ($duplicates->isNotEmpty())
        <div role="status" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm">
            <span class="font-semibold text-signal">{{ __('Possibly already in the list:') }}</span>
            @foreach ($duplicates as $dup)
                <a href="{{ route('students.edit', $dup) }}" wire:navigate class="underline">{{ $dup->fullName() }} ({{ $dup->date_of_birth->isoFormat('L') }})</a>@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-5">
        <x-ui.card>
            @include('livewire.students.partials.fields', ['prefix' => 'form', 'levels' => $levels])
            <p class="text-xs text-muted">{{ __('Fields marked with * are required.') }}</p>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                @if ($student?->exists)
                    <x-ui.button type="button" variant="ghost" wire:click="archive"
                                 wire:confirm="{{ $student->isArchived() ? __('Make :name active again?', ['name' => $student->fullName()]) : __('Archive :name? The student stays in all past bookings.', ['name' => $student->fullName()]) }}">
                        {{ $student->isArchived() ? __('Restore') : __('Archive') }}
                    </x-ui.button>
                @endif
            </div>
            <div class="flex gap-2">
                <x-ui.button variant="ghost" :href="route('students.index')" wire:navigate>{{ __('Cancel') }}</x-ui.button>
                <x-ui.button>{{ $student?->exists ? __('Save') : __('Create student') }}</x-ui.button>
            </div>
        </div>
    </form>
</div>
