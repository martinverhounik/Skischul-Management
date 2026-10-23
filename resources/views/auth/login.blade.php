<x-layouts.guest :title="__('Log in')">
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf
        <h1 class="font-display-narrow text-3xl">{{ __('Log in') }}</h1>

        @if (session('status'))
            <p role="status" class="rounded-lg bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
        @endif

        <x-ui.input :label="__('Email')" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />
        <x-ui.input :label="__('Password')" name="password" type="password" required autocomplete="current-password" />

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="size-4 accent-ink">
            {{ __('Stay logged in on this device') }}
        </label>

        <x-ui.button class="w-full">{{ __('Log in') }}</x-ui.button>

        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <a href="{{ route('password.request') }}" class="underline hover:text-signal">{{ __('Forgot your password?') }}</a>
            <span class="flex gap-2" aria-label="{{ __('Language') }}">
                <a href="?lang=de" class="{{ app()->getLocale() === 'de' ? 'font-semibold' : 'underline' }}">DE</a>
                <a href="?lang=en" class="{{ app()->getLocale() === 'en' ? 'font-semibold' : 'underline' }}">EN</a>
            </span>
        </div>
    </form>
</x-layouts.guest>
