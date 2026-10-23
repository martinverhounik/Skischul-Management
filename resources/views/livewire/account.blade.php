<div class="flex flex-col gap-5">
    @if ($status)
        <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.card :title="__('My account')">
            <form wire:submit="saveProfile" class="flex flex-col gap-4">
                <x-ui.input :label="__('Name')" name="name" wire:model="name" required autocomplete="name" />
                <div class="flex flex-col gap-1.5">
                    <span class="label-caps">{{ __('Email') }}</span>
                    <span class="text-sm">{{ auth()->user()->email }}</span>
                </div>
                <x-ui.select :label="__('Language')" name="locale" wire:model="locale" :options="['de' => 'Deutsch', 'en' => 'English']" required />
                <div><x-ui.button>{{ __('Save') }}</x-ui.button></div>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('Change password')">
            <form wire:submit="savePassword" class="flex flex-col gap-4">
                <x-ui.input :label="__('Current password')" name="current_password" type="password" wire:model="current_password" required autocomplete="current-password" />
                <x-ui.input :label="__('New password')" name="password" type="password" wire:model="password" required autocomplete="new-password" :hint="__('At least 10 characters, with letters and numbers.')" />
                <x-ui.input :label="__('Repeat new password')" name="password_confirmation" type="password" wire:model="password_confirmation" required autocomplete="new-password" />
                <div><x-ui.button>{{ __('Change password') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    </div>
</div>
