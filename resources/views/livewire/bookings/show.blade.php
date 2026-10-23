<div class="mx-auto flex w-full max-w-4xl flex-col gap-5">
    @if ($status)
        <div role="status" class="rounded-[var(--radius-card)] bg-ink px-5 py-3 text-sm text-white">{{ $status }}</div>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-3 px-1">
        <div class="flex flex-col gap-1">
            <span class="label-caps">{{ __('Booking') }} · {{ $booking->source->label() }}</span>
            <h1 class="font-display-narrow text-4xl">{{ $booking->number }} · {{ $booking->booker_name }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($booking->isCancelled())
                <span class="rounded-full bg-ink px-3 py-1 text-sm font-semibold text-white">{{ __('Cancelled') }} · {{ $booking->cancelled_at->timezone(auth()->user()->school->timezone)->isoFormat('L') }}</span>
            @else
                <button type="button" wire:click="togglePaid({{ $booking->id }})" class="rounded-full px-3 py-1 text-sm font-semibold {{ $booking->isPaid() ? 'bg-ink text-white' : 'bg-signal text-white' }}"
                        aria-label="{{ $booking->isPaid() ? __('Mark :number as unpaid', ['number' => $booking->number]) : __('Mark :number as paid', ['number' => $booking->number]) }}">{{ $booking->payment_status->label() }}</button>
                @if ($booking->isHandled())<span class="rounded-full bg-sand px-3 py-1 text-sm">{{ __('Handled') }}</span>@endif
            @endif
            <span class="rounded-full bg-white px-3 py-1 text-sm">{{ $booking->payment_method->label() }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 px-1">
        <x-ui.button variant="ghost" :href="route('bookings.index')" wire:navigate>← {{ __('All bookings') }}</x-ui.button>
        @unless ($booking->isCancelled())
            <x-ui.button type="button" variant="secondary" wire:click="toggleHandled({{ $booking->id }})">{{ $booking->isHandled() ? __('Back to active') : __('Mark as handled') }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" wire:click="editBooking({{ $booking->id }})">{{ __('Change booking') }}</x-ui.button>
            <x-ui.button type="button" variant="ghost" wire:click="cancelBooking({{ $booking->id }})"
                         wire:confirm="{{ __('Cancel booking :number? It stays in the list, marked as cancelled.', ['number' => $booking->number]) }}">{{ __('Cancel booking') }}</x-ui.button>
        @endunless
        @can('delete', $booking)
            <x-ui.button type="button" variant="danger" wire:click="deleteBooking({{ $booking->id }})"
                         wire:confirm="{{ __('Delete booking :number for good? This cannot be undone.', ['number' => $booking->number]) }}">{{ __('Delete') }}</x-ui.button>
        @endcan
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_320px]">
        <x-ui.card :title="__('Courses')">
            @foreach ($booking->lines as $line)
                <div wire:key="line-{{ $line->id }}" class="flex flex-col gap-2 rounded-xl bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="font-semibold">{{ $line->priceTable->displayName() }}</span>
                            <span class="text-sm text-muted">{{ $line->course_type->label() }} · {{ $line->sport->name }} · {{ $line->timeslot_key->label() }}</span>
                        </div>
                        <span class="tabular-nums">{{ App\Support\Money::format($line->price_cents) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($line->lessonDays as $day)
                            <span class="rounded-full bg-paper px-3 py-1 text-sm tabular-nums">
                                {{ $day->date->isoFormat('dd D.M.') }}@if ($day->starts_at) · {{ substr($day->starts_at, 0, 5) }}–{{ substr($day->ends_at, 0, 5) }}@endif
                            </span>
                        @endforeach
                    </div>
                    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @foreach ($line->students as $student)
                            <li>
                                <a href="{{ route('students.edit', $student) }}" class="underline-offset-2 hover:underline">{{ $student->fullName() }}</a>
                                <span class="text-muted">({{ $student->pivot->age_at_start }}, {{ $student->skillLevel?->name }})</span>
                                @if ($student->pivot->age_override)<span class="text-xs text-muted">· {{ __('age confirmed') }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            @foreach ($booking->addOns as $extra)
                <div class="flex justify-between rounded-xl bg-white px-4 py-3 text-sm">
                    <span>{{ $extra->quantity }} × {{ $extra->addOn->name }}</span>
                    <span class="tabular-nums">{{ App\Support\Money::format($extra->total_cents) }}</span>
                </div>
            @endforeach

            <dl class="ml-auto grid w-full max-w-xs grid-cols-2 gap-1 text-sm">
                <dt class="text-muted">{{ __('Subtotal') }}</dt><dd class="text-right tabular-nums">{{ App\Support\Money::format($booking->subtotal_cents) }}</dd>
                @if ($booking->discount_cents)
                    <dt class="text-muted">{{ __('Voucher :code', ['code' => $booking->voucher_code]) }}</dt><dd class="text-right tabular-nums">−{{ App\Support\Money::format($booking->discount_cents) }}</dd>
                @endif
                <dt class="font-semibold">{{ __('Total') }}</dt><dd class="text-right font-display-narrow text-2xl tabular-nums">{{ App\Support\Money::format($booking->total_cents) }}</dd>
            </dl>
        </x-ui.card>

        <div class="flex flex-col gap-5">
            <x-ui.card :title="__('Booker')">
                <address class="flex flex-col gap-0.5 text-sm not-italic">
                    <span class="font-semibold">{{ $booking->booker_name }}</span>
                    <span>{{ $booking->street }} {{ $booking->house_number }}</span>
                    <span>{{ $booking->postal_code }} {{ $booking->city }}, {{ $booking->country }}</span>
                    <a href="mailto:{{ $booking->email }}" class="mt-2 hover:text-signal">{{ $booking->email }}</a>
                    @if ($booking->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->phone) }}" class="hover:text-signal">{{ $booking->phone }}</a>@endif
                </address>
                @if ($booking->notes)<p class="text-sm text-muted">{{ $booking->notes }}</p>@endif
                <div class="flex flex-col gap-2 text-sm">
                    <span>{{ $booking->confirmation_sent_at ? __('Confirmation sent :when', ['when' => $booking->confirmation_sent_at->timezone(auth()->user()->school->timezone)->isoFormat('L LT')]) : __('No confirmation sent yet') }}</span>
                    <div><x-ui.button type="button" variant="secondary" class="h-9 px-4" wire:click="resendConfirmation">{{ __('Send confirmation again') }}</x-ui.button></div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('History')">
                <ol class="flex flex-col gap-2 text-sm">
                    @foreach ($booking->events as $event)
                        <li class="flex flex-col">
                            <span>{{ __('event.'.$event->action) }}</span>
                            <span class="text-xs text-muted">{{ $event->created_at->timezone(auth()->user()->school->timezone)->isoFormat('L LT') }} · {{ $event->user?->name ?? __('System') }}</span>
                        </li>
                    @endforeach
                </ol>
            </x-ui.card>
        </div>
    </div>
</div>
