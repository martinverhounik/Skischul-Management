<x-layouts.guest :title="__('Choose a new password')">
    <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <h1 class="font-display-narrow text-3xl">{{ __('Choose a new password') }}</h1>

        <x-ui.input :label="__('Email')" name="email" type="email" :value="old('email', $email)" required autocomplete="username" />
        <x-ui.input :label="__('New password')" name="password" type="password" required autocomplete="new-password" :hint="__('At least 10 characters, with letters and numbers.')" />
        <x-ui.input :label="__('Repeat new password')" name="password_confirmation" type="password" required autocomplete="new-password" />

        <x-ui.button class="w-full">{{ __('Save password') }}</x-ui.button>
    </form>
</x-layouts.guest>
