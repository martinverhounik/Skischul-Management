{{-- Bookings grouped by booker, with each course and its students underneath (US-14). --}}
@props(['bookings', 'canDelete' => false, 'empty'])
@if ($bookings->isEmpty())
    <p class="rounded-xl bg-white p-6 text-sm text-muted">{{ $empty }}</p>
@else
    <div class="relative overflow-x-auto rounded-xl bg-white">
        <table class="w-full min-w-[960px] text-left text-sm">
            <thead>
                <tr class="border-b border-hairline">
                    <th scope="col" class="label-caps px-4 py-3 font-normal">{{ __('Booking') }}</th>
                    <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Course') }}</th>
                    <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Dates') }}</th>
                    <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Students') }}</th>
                    <th scope="col" class="label-caps px-3 py-3 text-right font-normal">{{ __('Price') }}</th>
                    <th scope="col" class="label-caps px-3 py-3 font-normal">{{ __('Payment') }}</th>
                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            @foreach ($bookings as $booking)
                <tbody wire:key="booking-{{ $booking->id }}" class="border-b border-hairline last:border-0 {{ $booking->isCancelled() ? 'text-muted' : '' }}">
                    <tr class="bg-paper/60">
                        <th scope="rowgroup" colspan="4" class="px-4 py-2.5 text-left font-normal">
                            <a href="{{ route('bookings.show', $booking) }}" wire:navigate class="font-semibold hover:text-signal {{ $booking->isCancelled() ? 'line-through' : '' }}">{{ $booking->booker_name }}</a>
                            <span class="ml-2 text-xs tabular-nums text-muted">{{ $booking->number }}</span>
                            @if ($booking->isCancelled())
                                <span class="ml-2 rounded-full bg-ink px-2 py-0.5 text-xs font-semibold text-white no-underline">{{ __('Cancelled') }}</span>
                            @elseif ($booking->isHandled())
                                <span class="ml-2 rounded-full bg-sand px-2 py-0.5 text-xs">{{ __('Handled') }}</span>
                            @endif
                            @if ($booking->source->value !== 'office')
                                <span class="ml-2 rounded-full ring-1 ring-line px-2 py-0.5 text-xs">{{ $booking->source->label() }}</span>
                            @endif
                        </th>
                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums">{{ App\Support\Money::format($booking->total_cents) }}</td>
                        <td class="px-3 py-2.5">
                            @if ($booking->isCancelled())
                                <span class="text-xs">{{ $booking->payment_status->label() }}</span>
                            @else
                                <button type="button" wire:click="togglePaid({{ $booking->id }})"
                                        aria-label="{{ $booking->isPaid() ? __('Mark :number as unpaid', ['number' => $booking->number]) : __('Mark :number as paid', ['number' => $booking->number]) }}"
                                        class="rounded-full px-3 py-1 text-xs font-semibold {{ $booking->isPaid() ? 'bg-ink text-white hover:bg-ink-soft' : 'bg-signal text-white hover:bg-signal-dark' }}">
                                    {{ $booking->payment_status->label() }}
                                </button>
                            @endif
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex flex-wrap justify-end gap-1">
                                @unless ($booking->isCancelled())
                                    <button type="button" wire:click="toggleHandled({{ $booking->id }})" class="rounded-full px-3 py-1.5 text-xs ring-1 ring-ink hover:bg-ink hover:text-white">
                                        {{ $booking->isHandled() ? __('Back to active') : __('Handled') }}
                                    </button>
                                    <button type="button" wire:click="editBooking({{ $booking->id }})" class="rounded-full px-3 py-1.5 text-xs hover:bg-paper">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="cancelBooking({{ $booking->id }})"
                                            wire:confirm="{{ __('Cancel booking :number? It stays in the list, marked as cancelled.', ['number' => $booking->number]) }}"
                                            class="rounded-full px-3 py-1.5 text-xs hover:bg-paper">{{ __('Cancel booking') }}</button>
                                @endunless
                                @if ($canDelete)
                                    <button type="button" wire:click="deleteBooking({{ $booking->id }})"
                                            wire:confirm="{{ __('Delete booking :number for good? This cannot be undone.', ['number' => $booking->number]) }}"
                                            class="rounded-full px-3 py-1.5 text-xs text-signal hover:bg-paper">{{ __('Delete') }}</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @foreach ($booking->lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td class="px-4 py-2"></td>
                            <td class="px-3 py-2 align-top">
                                <span class="block">{{ $line->priceTable->displayName() }}</span>
                                <span class="text-xs text-muted">{{ $line->sport->name }} · {{ $line->course_type->label() }} · {{ $line->timeslot_key->label() }}</span>
                            </td>
                            <td class="px-3 py-2 align-top tabular-nums">
                                @if ($line->course_type->isHourly())
                                    {{ $line->start_date->isoFormat('dd D.M.') }} · {{ substr((string) $line->start_time, 0, 5) }}–{{ $line->endTime() }}
                                    <span class="block text-xs text-muted">{{ trans_choice(':count hour|:count hours', $line->units) }}</span>
                                @else
                                    {{ $line->start_date->isoFormat('dd D.M.') }} – {{ $line->end_date->isoFormat('dd D.M.') }}
                                    <span class="block text-xs text-muted">{{ trans_choice(':count day|:count days', $line->lesson_count) }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                <ul>
                                    @foreach ($line->students as $student)
                                        <li><a href="{{ route('students.edit', $student) }}" wire:navigate class="hover:text-signal">{{ $student->fullName() }}</a> <span class="text-xs text-muted">({{ $student->pivot->age_at_start }})</span></li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="px-3 py-2 text-right align-top tabular-nums text-muted">{{ App\Support\Money::format($line->price_cents) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>
@endif
