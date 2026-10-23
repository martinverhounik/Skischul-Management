<?php

namespace App\Livewire;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/** Each user's own name, language and password. */
class Account extends Component
{
    public string $name = '';

    public string $locale = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $status = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->locale = $user->preferredLocale();
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ], attributes: ['name' => __('Name'), 'locale' => __('Language')]);

        Auth::user()->fill($validated)->save();

        // Reload so the new language applies to the whole page.
        session()->flash('status', __('Your settings have been saved.', locale: $validated['locale']));
        $this->redirectRoute('account');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: [
            'current_password' => __('Current password'),
            'password' => __('New password'),
        ]);

        Auth::user()->forceFill(['password' => $this->password])->save();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->status = __('Your password has been changed.');
    }

    public function render()
    {
        return view('livewire.account')->title(__('My account'));
    }
}
