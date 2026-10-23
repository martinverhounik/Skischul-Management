<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Settings that other records point to cannot be deleted while in use
 * (US-30). Each model lists where it can be used in usages(); the list
 * grows as later build steps add price tables, students and bookings.
 */
trait GuardsDeletion
{
    /**
     * Human-readable place => number of records using this one.
     *
     * @return array<string, callable(): int>
     */
    abstract protected function usages(): array;

    /** @return array<string, int> only the places where it is actually used */
    public function usageCounts(): array
    {
        $counts = [];
        foreach ($this->usages() as $label => $count) {
            if (($n = $count()) > 0) {
                $counts[$label] = $n;
            }
        }

        return $counts;
    }

    public function isInUse(): bool
    {
        return $this->usageCounts() !== [];
    }

    /** Deletes the record, or explains where it is still used. */
    public function deleteIfUnused(): void
    {
        $counts = $this->usageCounts();

        if ($counts !== []) {
            $places = collect($counts)->map(fn ($n, $label) => "{$label} ({$n})")->implode(', ');

            throw ValidationException::withMessages([
                'delete' => __('":name" is still used in: :places. Remove it there first.', [
                    'name' => $this->getAttribute('name') ?? $this->getAttribute('code'),
                    'places' => $places,
                ]),
            ]);
        }

        $this->delete();
    }
}
