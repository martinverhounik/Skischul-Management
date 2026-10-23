@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 px-4 py-10">
        <div class="flex flex-col gap-1 px-2">
            <span class="label-caps">{{ config('app.name') }}</span>
            <span class="font-display-narrow text-5xl text-signal">{{ __('Back office') }}</span>
        </div>

        <div class="rounded-[var(--radius-card)] bg-paper p-6 sm:p-8">
            {{ $slot }}
        </div>

        <p class="label-caps px-2 text-center">{{ __('Access for ski school staff only') }}</p>
    </main>
</body>
</html>
