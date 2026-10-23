<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the language for this request: the user's own choice, then their
 * school's default, then the app default. Guests can switch with ?lang=.
 */
class SetLocale
{
    public const SUPPORTED = ['de', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $locale = $request->user()->preferredLocale();
        } else {
            $requested = $request->query('lang');
            if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
                $request->session()->put('locale', $requested);
            }
            $locale = $request->session()->get('locale', config('app.locale'));
        }

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
