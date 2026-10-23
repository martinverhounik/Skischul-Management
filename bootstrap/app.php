<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetCurrentSchool;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            SetCurrentSchool::class,
            SetLocale::class,
        ]);

        // Set the current school BEFORE route-model binding, so a record id
        // in the URL (e.g. /settings/price-tables/5/edit) is looked up inside
        // the user's own school only.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentSchool::class,
        );
        $middleware->prependToPriorityList(
            before: SetCurrentSchool::class,
            prepend: EnsureUserIsActive::class,
        );

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
