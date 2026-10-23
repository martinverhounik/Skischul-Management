@props(['variant' => 'primary', 'type' => 'submit', 'href' => null])
@php
    $classes = match ($variant) {
        'primary' => 'bg-ink text-white hover:bg-ink-soft',
        'secondary' => 'bg-white text-ink hover:bg-sand',
        'danger' => 'bg-white text-signal ring-1 ring-signal hover:bg-signal hover:text-white',
        'ghost' => 'bg-transparent text-ink hover:bg-white',
    };
    $base = 'inline-flex h-11 items-center justify-center gap-2 rounded-full px-5 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$base, $classes]) }}>{{ $slot }}</button>
@endif
