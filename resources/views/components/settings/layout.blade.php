@props(['status' => null, 'section'])
@php
    $current = $section;
    $sections = [
        ['route' => 'settings.general', 'label' => __('General')],
        ['route' => 'settings.season', 'label' => __('Season & closed days')],
        ['route' => 'settings.times', 'label' => __('Course times')],
        ['route' => 'settings.sports', 'label' => __('Sports')],
        ['route' => 'settings.skill-levels', 'label' => __('Skill levels')],
        ['route' => 'settings.age-groups', 'label' => __('Age groups')],
        ['route' => 'settings.meeting-points', 'label' => __('Meeting points')],
        ['route' => 'settings.add-ons', 'label' => __('Add-ons')],
        ['route' => 'settings.vouchers', 'label' => __('Vouchers')],
    ];
@endphp
<div class="flex flex-wrap items-start gap-5">
    <nav aria-label="{{ __('Settings') }}" class="flex w-full flex-col gap-1 rounded-[var(--radius-card)] bg-paper p-3 lg:w-64 lg:flex-none">
        <span class="label-caps px-3 pb-1 pt-2">{{ __('Settings') }}</span>
        @foreach ($sections as $section)
            @php($active = $section['route'] === 'settings.'.$current)
            <a href="{{ route($section['route']) }}" wire:navigate
               @if ($active) aria-current="page" @endif
               class="rounded-full px-4 py-2.5 text-sm {{ $active ? 'bg-ink text-white' : 'hover:bg-white' }}">{{ $section['label'] }}</a>
        @endforeach
    </nav>

    <div class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
        @if ($status)
            <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
        @endif
        @error('delete')
            <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal">{{ $message }}</div>
        @enderror
        {{ $slot }}
    </div>
</div>
