<div class="flex flex-col gap-5">
    @if ($status)
        <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
    @endif

    @error('user')
        <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal">{{ $message }}</div>
    @enderror

    <x-ui.card :title="__('Users')">
        <x-slot:actions>
            <x-ui.button type="button" wire:click="$toggle('showInvite')" :variant="$showInvite ? 'secondary' : 'primary'">
                {{ $showInvite ? __('Cancel') : __('Invite user') }}
            </x-ui.button>
        </x-slot:actions>

        @if ($showInvite)
            <form wire:submit="invite" class="grid gap-4 rounded-xl bg-white p-4 sm:grid-cols-[1fr_1fr_220px_auto] sm:items-end">
                <x-ui.input :label="__('Name')" name="name" wire:model="name" required autocomplete="off" />
                <x-ui.input :label="__('Email')" name="email" type="email" wire:model="email" required autocomplete="off" />
                <x-ui.select :label="__('Role')" name="role" wire:model="role" :options="$roles" required />
                <x-ui.button>{{ __('Send invitation') }}</x-ui.button>
                <p class="text-xs text-muted sm:col-span-4">{{ __('The person gets an email with a link to set their own password. The link is valid for 72 hours.') }}</p>
            </form>
        @endif

        <div class="relative overflow-x-auto rounded-xl bg-white">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead>
                    <tr class="border-b border-hairline">
                        <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Name') }}</th>
                        <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Role') }}</th>
                        <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Status') }}</th>
                        <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Last login') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="border-b border-hairline last:border-0 {{ $user->is_active ? '' : 'text-muted' }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span aria-hidden="true" class="flex size-9 flex-none items-center justify-center rounded-full {{ $user->is_active ? 'bg-sand' : 'bg-paper' }} text-xs font-semibold">{{ Str::of($user->name)->explode(' ')->map(fn ($p) => Str::substr($p, 0, 1))->take(2)->implode('') }}</span>
                                    <div class="flex flex-col">
                                        <span class="font-semibold">{{ $user->name }}@if ($user->is(auth()->user())) <span class="font-normal text-muted">({{ __('you') }})</span>@endif</span>
                                        <span class="text-xs text-muted">{{ $user->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <label class="sr-only" for="role-{{ $user->id }}">{{ __('Role of :name', ['name' => $user->name]) }}</label>
                                <select id="role-{{ $user->id }}"
                                        wire:change="changeRole({{ $user->id }}, $event.target.value)"
                                        @disabled(! $user->is_active)
                                        class="h-9 rounded-lg border border-line bg-white px-2 text-sm">
                                    @foreach ($roles as $value => $label)
                                        <option value="{{ $value }}" @selected($user->role->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                @if (! $user->is_active)
                                    <span class="rounded-full bg-paper px-2.5 py-1 text-xs">{{ __('Deactivated') }}</span>
                                @elseif ($user->isPendingInvite())
                                    <span class="rounded-full bg-signal px-2.5 py-1 text-xs font-semibold text-white">{{ __('Invited') }}</span>
                                @else
                                    <span class="rounded-full bg-ink px-2.5 py-1 text-xs text-white">{{ __('Active') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ $user->last_login_at?->timezone(auth()->user()->school->timezone)->isoFormat('L LT') ?? '–' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @if ($user->is_active && $user->isPendingInvite())
                                        <x-ui.button type="button" variant="ghost" class="h-9 px-3" wire:click="resendInvite({{ $user->id }})">{{ __('Resend invitation') }}</x-ui.button>
                                    @endif
                                    @if ($user->is_active)
                                        @can('deactivate', $user)
                                            <x-ui.button type="button" variant="danger" class="h-9 px-3"
                                                         wire:click="deactivate({{ $user->id }})"
                                                         wire:confirm="{{ __('Deactivate :name? They will be logged out immediately.', ['name' => $user->name]) }}">
                                                {{ __('Deactivate') }}
                                            </x-ui.button>
                                        @endcan
                                    @else
                                        <x-ui.button type="button" variant="secondary" class="h-9 px-3" wire:click="reactivate({{ $user->id }})">{{ __('Reactivate') }}</x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
