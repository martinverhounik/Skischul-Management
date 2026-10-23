@props(['title' => null, 'dark' => false])
<section {{ $attributes->class([
    'flex flex-col gap-5 rounded-[var(--radius-card)] p-5 sm:p-6',
    'bg-paper text-ink' => ! $dark,
    'bg-ink text-white' => $dark,
]) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-end justify-between gap-3">
            @if ($title)
                <h2 class="font-display-narrow text-3xl">{{ $title }}</h2>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
    {{ $slot }}
</section>
