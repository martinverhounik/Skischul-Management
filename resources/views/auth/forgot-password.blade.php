<x-layouts.guest :title="__('Forgot your password?')">
    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf
        <h1 class="font-display-narrow text-3xl">{{ __('Forgot your password?') }}</h1>
        <p class="text-sm text-muted">{{ __('Enter your email address and we will send you a link to choose a new password.') }}</p>

        @if (session('status'))
            <p role="status" class="rounded-lg bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
        @endif

        <x-ui.input :label="__('Email')" name="email" type="email" :value="old('email')" required autofocus autocomplete="username" />

        <x-ui.button class="w-full">{{ __('Send link') }}</x-ui.button>

        <a href="{{ route('login') }}" class="text-sm underline hover:text-signal">{{ __('Back to login') }}</a>
    </form>
</x-layouts.guest>
