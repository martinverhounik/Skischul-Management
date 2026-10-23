<?php

namespace App\Livewire\Concerns;

/** A short confirmation line shown above a settings section. */
trait ConfirmsChanges
{
    public ?string $status = null;

    protected function saved(?string $message = null): void
    {
        $this->status = $message ?? __('Saved.');
    }
}
