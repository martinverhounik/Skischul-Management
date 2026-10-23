<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request (also safe under Octane / queue workers).
        $this->app->scoped(CurrentSchool::class);
    }

    public function boot(): void
    {
        // Owner-only areas: school settings, price tables, users and
        // instructor financial data (US-27). Checked on the server, not
        // just hidden in the menu.
        Gate::define('admin', fn (User $user): bool => $user->isOwner());

        Password::defaults(fn () => Password::min(10)->letters()->numbers());
    }
}
