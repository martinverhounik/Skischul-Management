<?php

namespace Tests;

use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't need the compiled CSS/JS.
        $this->withoutVite();
    }

    /**
     * Log in as the user AND make their school the current tenant.
     * Livewire::test() skips route middleware, so the tenant has to be set
     * by hand there; for HTTP requests the middleware does it anyway.
     */
    protected function signIn(User $user): static
    {
        app(CurrentSchool::class)->set($user->school);

        return $this->actingAs($user);
    }
}
