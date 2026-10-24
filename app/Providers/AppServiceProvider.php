<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
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
        if ($this->app->isLocal() && config('app.demo_today')) {
            $now = Carbon::parse(config('app.demo_today').' 10:00', 'Europe/Vienna');
            Carbon::setTestNow($now);
            CarbonImmutable::setTestNow($now->toImmutable());
        }

        // Owner-only areas: school settings, price tables, users and
        // instructor financial data (US-27). Checked on the server, not
        // just hidden in the menu.
        Gate::define('admin', fn (User $user): bool => $user->isOwner());

        Password::defaults(fn () => Password::min(10)->letters()->numbers());
    }
}
