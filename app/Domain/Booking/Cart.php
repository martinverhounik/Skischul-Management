<?php

namespace App\Domain\Booking;

use App\Models\AddOn;
use App\Models\School;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * The office's shopping cart (US-12), kept in the user's session and per
 * school. It only stores the choices; prices and rules are always worked
 * out fresh by BookingRules, so a changed setting can't leave a stale price.
 *
 * Editing an existing booking (US-16) uses its own cart, forBooking($id),
 * so a half-finished new booking is not mixed up with it.
 */
class Cart
{
    private ?int $bookingId = null;

    public function __construct(private Session $session, private BookingRules $rules) {}

    /** The cart used while changing an existing booking (null = a new booking). */
    public function forBooking(?int $bookingId): self
    {
        $cart = clone $this;
        $cart->bookingId = $bookingId;

        return $cart;
    }

    public function bookingId(): ?int
    {
        return $this->bookingId;
    }

    /** Replace the whole content, e.g. when loading a booking for editing. */
    public function fill(School $school, array $items, array $addOns): void
    {
        $data = ['items' => [], 'add_ons' => []];
        foreach ($items as $item) {
            $data['items'][(string) Str::uuid()] = $this->clean($item);
        }
        foreach ($addOns as $id => $quantity) {
            if ($quantity > 0) {
                $data['add_ons'][(int) $id] = (int) $quantity;
            }
        }
        $this->put($school, $data);
    }

    private function key(School $school): string
    {
        return $this->bookingId ? "cart.{$school->id}.booking.{$this->bookingId}" : "cart.{$school->id}";
    }

    private function data(School $school): array
    {
        return $this->session->get($this->key($school), ['items' => [], 'add_ons' => []]);
    }

    private function put(School $school, array $data): void
    {
        $this->session->put($this->key($school), $data);
    }

    /** @return array<string, array> key => item */
    public function items(School $school): array
    {
        return $this->data($school)['items'];
    }

    public function item(School $school, string $key): ?array
    {
        return $this->items($school)[$key] ?? null;
    }

    public function add(School $school, array $item): string
    {
        $data = $this->data($school);
        $key = (string) Str::uuid();
        $data['items'][$key] = $this->clean($item);
        $this->put($school, $data);

        return $key;
    }

    public function update(School $school, string $key, array $item): void
    {
        $data = $this->data($school);
        if (isset($data['items'][$key])) {
            $data['items'][$key] = $this->clean($item);
            $this->put($school, $data);
        }
    }

    public function remove(School $school, string $key): void
    {
        $data = $this->data($school);
        unset($data['items'][$key]);
        $this->put($school, $data);
    }

    /** @return array<int, int> add_on_id => quantity */
    public function addOns(School $school): array
    {
        return $this->data($school)['add_ons'];
    }

    public function setAddOn(School $school, int $addOnId, int $quantity): void
    {
        $data = $this->data($school);
        if ($quantity > 0) {
            $data['add_ons'][$addOnId] = min($quantity, 99);
        } else {
            unset($data['add_ons'][$addOnId]);
        }
        $this->put($school, $data);
    }

    public function clear(School $school): void
    {
        $this->session->forget($this->key($school));
    }

    public function isEmpty(School $school): bool
    {
        return $this->items($school) === [];
    }

    /** @return array<string, Quote> key => fresh quote */
    public function quotes(School $school): array
    {
        return array_map(fn ($item) => $this->rules->check($school, $item), $this->items($school));
    }

    /**
     * Active add-ons with their quantities and totals.
     *
     * @return list<array{add_on: AddOn, quantity: int, total: int}>
     */
    public function addOnLines(School $school): array
    {
        $chosen = $this->addOns($school);
        if ($chosen === []) {
            return [];
        }

        return AddOn::query()->whereKey(array_keys($chosen))->where('is_active', true)->orderBy('name')->get()
            ->map(fn (AddOn $a) => ['add_on' => $a, 'quantity' => $chosen[$a->id], 'total' => $a->price_cents * $chosen[$a->id]])
            ->all();
    }

    public function subtotal(School $school): int
    {
        $courses = array_sum(array_map(fn (Quote $q) => $q->isValid() ? $q->priceCents : 0, $this->quotes($school)));
        $extras = array_sum(array_column($this->addOnLines($school), 'total'));

        return $courses + $extras;
    }

    private function clean(array $item): array
    {
        $students = collect($item['students'] ?? [])
            ->map(fn ($s) => ['id' => (int) $s['id'], 'age_override' => (bool) ($s['age_override'] ?? false)])
            ->unique('id')->values()->all();

        return [
            'price_table_id' => (int) ($item['price_table_id'] ?? 0),
            'sport_id' => (int) ($item['sport_id'] ?? 0),
            'timeslot_key' => (string) ($item['timeslot_key'] ?? ''),
            'start_date' => (string) ($item['start_date'] ?? ''),
            'days' => (int) ($item['days'] ?? 0),
            'start_time' => (string) ($item['start_time'] ?? ''),
            'hours' => (int) ($item['hours'] ?? 0),
            'students' => $students,
        ];
    }
}
