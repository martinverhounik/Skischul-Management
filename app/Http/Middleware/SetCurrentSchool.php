<?php

namespace App\Http\Middleware;

use App\Support\CurrentSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the logged-in user's school the current tenant for this request,
 * so every BelongsToSchool model is filtered to it.
 */
class SetCurrentSchool
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $this->currentSchool->set($user->school);
        }

        return $next($request);
    }
}
