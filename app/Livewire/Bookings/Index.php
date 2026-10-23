<?php

namespace App\Livewire\Bookings;

use App\Livewire\Concerns\ManagesBookings;
use App\Models\Booking;
use App\Models\BookingLine;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * All bookings (US-14): active bookings first, handled and cancelled ones
 * in a second table below. Listed by booker, with the students underneath.
 */
class Index extends Component
{
    use ManagesBookings, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'payment', except: '')]
    public string $payment = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'payment'], true)) {
            $this->resetPage('done');
        }
    }

    private function base(): Builder
    {
        $term = trim($this->search);

        return Booking::query()
            ->with(['lines.priceTable.sports', 'lines.sport', 'lines.students', 'lines.lessonDays'])
            ->when($this->payment !== '', fn ($q) => $q->where('payment_status', $this->payment))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w
                    ->where('booker_name', 'like', $like)
                    ->orWhere('number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('lines.students', fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
            });
    }

    public function render()
    {
        $active = $this->base()->whereNull('handled_at')->whereNull('cancelled_at')
            ->orderBy(BookingLine::query()->selectRaw('min(start_date)')->whereColumn('booking_lines.booking_id', 'bookings.id'))
            ->get();

        $done = $this->base()->where(fn ($q) => $q->whereNotNull('handled_at')->orWhereNotNull('cancelled_at'))
            ->latest('updated_at')
            ->paginate(25, pageName: 'done');

        return view('livewire.bookings.index', [
            'active' => $active,
            'done' => $done,
            'canDelete' => auth()->user()->isOwner(),
        ])->title(__('Bookings'));
    }
}
