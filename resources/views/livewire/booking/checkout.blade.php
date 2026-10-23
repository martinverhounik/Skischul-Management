<div class="flex flex-wrap items-start gap-5">
    <form wire:submit="placeOrder" class="flex min-w-0 flex-[999_1_560px] flex-col gap-5">
        <div class="flex flex-col gap-1 px-1">
            <a href="{{ route('booking', $editedBooking ? ['booking' => $editedBooking->id] : []) }}" wire:navigate class="label-caps hover:text-signal">← {{ __('Back to booking') }}</a>
            <h1 class="font-display-narrow text-4xl">{{ $editedBooking ? __('Save changes to :number', ['number' => $editedBooking->number]) : __('Checkout') }}</h1>
        </div>

        @error('cart')
            <div role="alert" class="rounded-[var(--radius-card)] bg-white px-5 py-3 text-sm text-signal">{{ $message }}</div>
        @enderror

        <x-ui.card :title="__('Booker')">
            @if ($participants->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-muted">{{ __('Same as:') }}</span>
                    @foreach ($participants as $name)
                        <button type="button" wire:click="useParticipant(@js($name))" class="rounded-full bg-white px-3 py-1.5 ring-1 ring-line hover:bg-paper">{{ $name }}</button>
                    @endforeach
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-ui.input :label="__('Full name of the booker')" name="booker_name" wire:model="booker_name" required autocomplete="off" />
                </div>
                <x-ui.input :label="__('Email')" name="email" type="email" wire:model="email" required autocomplete="off" />
                <x-ui.input :label="__('Phone')" name="phone" type="tel" wire:model="phone" :hint="__('Optional')" />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Billing address')">
            <div class="grid gap-4 sm:grid-cols-[1fr_140px]">
                <x-ui.input :label="__('Street')" name="street" wire:model="street" required />
                <x-ui.input :label="__('House number')" name="house_number" wire:model="house_number" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-[140px_1fr_1fr]">
                <x-ui.input :label="__('Postal code')" name="postal_code" wire:model="postal_code" required />
                <x-ui.input :label="__('City')" name="city" wire:model="city" required />
                <x-ui.select :label="__('Country')" name="country" wire:model="country" :options="$countries" required />
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Payment')">
            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="sr-only">{{ __('Payment method') }}</legend>
                @foreach (App\Enums\PaymentMethod::cases() as $method)
                    <label wire:key="pm-{{ $method->value }}" class="flex cursor-pointer items-center gap-3 rounded-xl p-4 ring-1 {{ $payment_method === $method->value ? 'bg-ink text-white ring-ink' : 'bg-white ring-line' }}">
                        <input type="radio" value="{{ $method->value }}" wire:model.live="payment_method" class="sr-only">
                        <span class="font-semibold">{{ $method->label() }}</span>
                    </label>
                @endforeach
            </fieldset>
            <p class="text-xs text-muted">{{ __('The booking starts as unpaid. Mark it as paid in the booking list once the money is in.') }}</p>
            <div class="flex flex-col gap-1.5">
                <label for="notes" class="label-caps">{{ __('Notes') }}</label>
                <textarea id="notes" wire:model="notes" rows="2" class="rounded-lg border border-line bg-white px-3 py-2 text-sm"></textarea>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-end gap-4">
            @if ($editedBooking)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="sendUpdate" class="size-4 accent-ink">
                    {{ __('Send the booker an updated confirmation') }}
                </label>
            @endif
            <x-ui.button class="h-12 px-8" :disabled="! $allValid" wire:loading.attr="disabled">{{ $editedBooking ? __('Save changes') : __('Place booking') }}</x-ui.button>
        </div>
    </form>

    {{-- Summary --}}
    <aside aria-label="{{ __('Summary') }}" class="flex w-full flex-col gap-4 rounded-[var(--radius-card)] bg-ink p-5 text-white lg:sticky lg:top-4 lg:w-[380px] lg:flex-none">
        <h2 class="font-display-narrow text-3xl">{{ __('Summary') }}</h2>
        <ul class="flex flex-col gap-3">
            @foreach ($quotes as $key => $q)
                <li wire:key="sum-{{ $key }}" class="flex flex-col gap-1 rounded-xl bg-ink-soft p-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="font-semibold">{{ $q->priceTable?->displayName() }}</span>
                        <span class="tabular-nums">{{ $q->isValid() ? App\Support\Money::format($q->priceCents) : '–' }}</span>
                    </div>
                    @if ($q->startDate())
                        <span class="text-xs text-white/70">
                            @if ($q->priceTable->course_type->isHourly())
                                {{ $q->startDate()->isoFormat('dd, L') }} · {{ $q->startTime }}–{{ $q->endTime }}
                            @else
                                {{ $q->lessonDates->map(fn ($d) => $d->isoFormat('dd D.M.'))->implode(', ') }} · {{ $q->option?->label() }}
                            @endif
                        </span>
                    @endif
                    <span class="text-xs text-white/70">{{ collect($q->participants)->map(fn ($p) => $p['student']->fullName())->implode(', ') }}</span>
                    @unless ($q->isValid())
                        <span class="text-xs font-semibold">{{ collect($q->errors)->first() ?? collect($q->ageWarnings)->first() }} <a href="{{ route('booking') }}" class="underline">{{ __('Fix in the cart') }}</a></span>
                    @endunless
                </li>
            @endforeach
        </ul>

        @foreach ($addOnLines as $line)
            <div class="flex justify-between text-sm"><span>{{ $line['quantity'] }} × {{ $line['add_on']->name }}</span><span class="tabular-nums">{{ App\Support\Money::format($line['total']) }}</span></div>
        @endforeach

        <div class="flex flex-col gap-2 border-t border-white/15 pt-4">
            @if ($voucherCode)
                <div class="flex items-center justify-between text-sm">
                    <span>{{ __('Voucher :code', ['code' => $voucherCode]) }} @unless ($editedBooking)<button type="button" wire:click="removeVoucher" class="ml-1 underline">{{ __('remove') }}</button>@endunless</span>
                    <span class="tabular-nums">−{{ App\Support\Money::format($discount) }}</span>
                </div>
            @elseif (! $editedBooking)
                <form wire:submit="applyVoucher" class="flex gap-2">
                    <label for="voucher" class="sr-only">{{ __('Voucher code') }}</label>
                    <input id="voucher" wire:model="voucherInput" placeholder="{{ __('Voucher code') }}" class="h-10 min-w-0 flex-1 rounded-full bg-white px-4 text-sm uppercase text-ink">
                    <button type="submit" class="h-10 rounded-full bg-white/10 px-4 text-sm hover:bg-white/20">{{ __('Apply') }}</button>
                </form>
                @error('voucherInput')<p class="text-sm font-semibold">{{ $message }}</p>@enderror
            @endif
        </div>

        <div class="flex items-baseline justify-between border-t border-white/15 pt-4">
            <span class="label-caps !text-white/60">{{ __('Total') }}</span>
            <span class="font-display-narrow text-4xl tabular-nums">{{ App\Support\Money::format($total) }}</span>
        </div>
    </aside>
</div>
