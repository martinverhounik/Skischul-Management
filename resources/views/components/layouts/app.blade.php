@props(['title' => null])
@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard', 'label' => __('Dashboard'), 'show' => true, 'match' => 'dashboard'],
        ['route' => 'booking', 'label' => __('Booking'), 'show' => true, 'match' => ['booking', 'booking.*']],
        ['route' => 'bookings.index', 'label' => __('Bookings'), 'show' => true, 'match' => 'bookings.*'],
        ['route' => 'grouping', 'label' => __('Grouping'), 'show' => true, 'match' => 'grouping'],
        ['route' => 'students.index', 'label' => __('Students'), 'show' => true, 'match' => 'students.*'],
        ['route' => 'instructors.index', 'label' => __('Instructors'), 'show' => true, 'match' => 'instructors.*'],
        ['route' => 'users.index', 'label' => __('Users'), 'show' => $user->can('viewAny', App\Models\User::class), 'match' => 'users.*'],
        ['route' => 'settings.general', 'label' => __('Settings'), 'show' => $user->can('admin'), 'match' => 'settings.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ $user->school->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen flex-col gap-5 p-4 sm:p-6">
        <header class="flex flex-wrap items-center justify-between gap-4 px-1">
            <div class="flex flex-col gap-1">
                <span class="label-caps">{{ $user->school->name }}</span>
                <a href="{{ route('dashboard') }}" class="font-display-narrow text-4xl text-signal sm:text-5xl">{{ __('Back office') }}</a>
            </div>

            <nav aria-label="{{ __('Main navigation') }}" class="flex flex-wrap items-center gap-1 rounded-full bg-paper p-1">
                @foreach ($nav as $item)
                    @continue(! $item['show'])
                    @php($active = request()->routeIs($item['match']))
                    <a href="{{ route($item['route']) }}"
                       @if ($active) aria-current="page" @endif
                       class="rounded-full px-4 py-2 text-sm font-medium {{ $active ? 'bg-ink text-white' : 'text-ink hover:bg-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('account') }}" class="flex items-center gap-2 rounded-full bg-paper py-1 pl-1 pr-4 text-sm hover:bg-white">
                    <span aria-hidden="true" class="flex size-9 items-center justify-center rounded-full bg-sand text-xs font-semibold">{{ Str::of($user->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->implode('') }}</span>
                    <span>{{ $user->name }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="h-11 rounded-full px-4 text-sm text-ink hover:bg-paper">{{ __('Log out') }}</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ session('status') }}</div>
        @endif

        <main class="flex flex-1 flex-col gap-5">
            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
