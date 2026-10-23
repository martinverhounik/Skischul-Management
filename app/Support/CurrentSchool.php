<?php

namespace App\Support;

use App\Models\School;

/**
 * Holds the school (tenant) of the current request.
 *
 * Set by the SetCurrentSchool middleware from the logged-in user. Every model
 * using the BelongsToSchool trait is automatically filtered to this school
 * and new records are automatically assigned to it.
 */
class CurrentSchool
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function get(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    public function has(): bool
    {
        return $this->school !== null;
    }

    /**
     * Run a callback as if the given school were current, then restore the
     * previous one. Useful in console commands, jobs and tests.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(School $school, callable $callback): mixed
    {
        $previous = $this->school;
        $this->school = $school;

        try {
            return $callback();
        } finally {
            $this->school = $previous;
        }
    }
}
