<?php

namespace App\Livewire\Concerns;

use App\Models\School;
use App\Support\CurrentSchool;

/**
 * For owner-only Livewire pages (US-27). The check runs on EVERY request
 * to the component, not only when the page first loads, because Livewire
 * actions can be called directly.
 */
trait RequiresAdmin
{
    public function bootRequiresAdmin(): void
    {
        $this->authorize('admin');
    }

    protected function school(): School
    {
        return app(CurrentSchool::class)->get();
    }
}
