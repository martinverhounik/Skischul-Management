<div class="flex flex-col gap-5">
    @if ($status)
        <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
    @endif

    <x-ui.card :title="__('Bookings')">
        <x-slot:actions>
            <x-ui.button :href="route('booking')" wire:navigate>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="square" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
                {{ __('New booking') }}
            </x-ui.button>
        </x-slot:actions>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex min-w-[240px] flex-1 flex-col gap-1.5">
                <label for="search" class="label-caps">{{ __('Search') }}</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Booker, student, email or booking number') }}"
                       class="h-11 w-full rounded-full border border-line bg-white px-4 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="payment" class="label-caps">{{ __('Payment') }}</label>
                <select id="payment" wire:model.live="payment" class="h-11 rounded-full border border-line bg-white px-4 text-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (App\Enums\PaymentStatus::cases() as $p)<option value="{{ $p->value }}">{{ $p->label() }}</option>@endforeach
                </select>
            </div>
        </div>

        <section aria-labelledby="active-heading" class="flex flex-col gap-3">
            <h3 id="active-heading" class="flex items-baseline gap-2 font-semibold">{{ __('Active') }} <span class="text-sm font-normal text-muted">{{ $active->count() }}</span></h3>
            <x-bookings.table :bookings="$active" :can-delete="$canDelete" :empty="__('No active bookings.')" />
        </section>

        <section aria-labelledby="done-heading" class="flex flex-col gap-3">
            <h3 id="done-heading" class="flex items-baseline gap-2 font-semibold">{{ __('Handled and cancelled') }} <span class="text-sm font-normal text-muted">{{ $done->total() }}</span></h3>
            <x-bookings.table :bookings="$done" :can-delete="$canDelete" :empty="__('Nothing here yet.')" />
            {{ $done->links() }}
        </section>
    </x-ui.card>
</div>
