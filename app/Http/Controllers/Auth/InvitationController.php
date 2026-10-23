<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/** An invited user sets their first password (US-31). */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $user = $this->pendingUser($request->query('email'));
        $valid = $user && Password::broker('invites')->tokenExists($user, $token);

        return view('auth.accept-invitation', [
            'token' => $token,
            'email' => $request->query('email'),
            'valid' => $valid,
            'name' => $user?->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $broker = Password::broker('invites');
        $user = $this->pendingUser($request->input('email'));

        if (! $user || ! $broker->tokenExists($user, $request->string('token')->toString())) {
            return back()->withErrors(['email' => __('This invitation link is invalid or has expired. Ask an administrator to send a new one.')]);
        }

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'email_verified_at' => now(),
        ])->save();

        $broker->deleteToken($user);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('dashboard')->with('status', __('Welcome! Your password has been set.'));
    }

    private function pendingUser(mixed $email): ?User
    {
        if (! is_string($email)) {
            return null;
        }

        return User::query()
            ->where('email', mb_strtolower($email))
            ->where('is_active', true)
            ->whereNull('password')
            ->first();
    }
}
