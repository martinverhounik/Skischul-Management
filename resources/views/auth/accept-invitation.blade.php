<x-layouts.guest :title="__('Set your password')">
    @if ($valid)
        <form method="POST" action="{{ route('invitation.store') }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <h1 class="font-display-narrow text-3xl">{{ __('Welcome, :name', ['name' => $name]) }}</h1>
            <p class="text-sm text-muted">{{ __('Choose a password for :email to finish setting up your account.', ['email' => $email]) }}</p>

            @error('email')
                <p role="alert" class="rounded-lg bg-white px-4 py-3 text-sm text-signal">{{ $message }}</p>
            @enderror

            <x-ui.input :label="__('Password')" name="password" type="password" required autofocus autocomplete="new-password" :hint="__('At least 10 characters, with letters and numbers.')" />
            <x-ui.input :label="__('Repeat password')" name="password_confirmation" type="password" required autocomplete="new-password" />

            <x-ui.button class="w-full">{{ __('Set password and log in') }}</x-ui.button>
        </form>
    @else
        <div class="flex flex-col gap-4">
            <h1 class="font-display-narrow text-3xl">{{ __('Link not valid') }}</h1>
            <p class="text-sm">{{ __('This invitation link is invalid or has expired. Ask an administrator to send a new one.') }}</p>
            <a href="{{ route('login') }}" class="text-sm underline hover:text-signal">{{ __('Back to login') }}</a>
        </div>
    @endif
</x-layouts.guest>
