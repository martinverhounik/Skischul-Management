<?php

use App\Livewire\Users\Index;
use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

// SaaS readiness: one school must never see or change another school's data.

beforeEach(function () {
    $this->alpbach = School::factory()->create(['name' => 'Alpbach']);
    $this->lech = School::factory()->create(['name' => 'Lech']);

    $this->alpbachOwner = User::factory()->forSchool($this->alpbach)->owner()->create(['name' => 'Alpbach Owner']);
    $this->lechOwner = User::factory()->forSchool($this->lech)->owner()->create(['name' => 'Lech Owner']);
    $this->lechStaff = User::factory()->forSchool($this->lech)->create(['name' => 'Lech Staff']);
});

it('answers 404 for another school\'s user in the browser', function () {
    $this->actingAs($this->alpbachOwner);

    $deactivate = fn (User $user) => $this->postJson('/livewire/update', [
        'components' => [[
            'snapshot' => json_encode(Livewire::test(Index::class)->snapshot),
            'updates' => [],
            'calls' => [['path' => '', 'method' => 'deactivate', 'params' => [$user->id]]],
        ]],
    ], ['X-Livewire' => 'true']);

    $deactivate($this->lechStaff)->assertNotFound();
    expect($this->lechStaff->fresh()->is_active)->toBeTrue();

    // Control: the same request works for a user of your own school.
    $ownStaff = User::factory()->forSchool($this->alpbach)->create();
    $deactivate($ownStaff)->assertOk();
    expect($ownStaff->fresh()->is_active)->toBeFalse();
});

it('only lists users of your own school', function () {
    $this->actingAs($this->alpbachOwner)->get('/users')
        ->assertSee('Alpbach Owner')
        ->assertDontSee('Lech Owner')
        ->assertDontSee('Lech Staff');
});

it('filters every query to the current school', function () {
    app(CurrentSchool::class)->set($this->lech);

    expect(User::pluck('name')->sort()->values()->all())->toBe(['Lech Owner', 'Lech Staff']);
});

it('cannot change users of another school', function (string $method, array $args) {
    $this->signIn($this->alpbachOwner);

    // Another school's user simply doesn't exist from here (a 404 in the browser).
    expect(fn () => Livewire::test(Index::class)->call($method, $this->lechStaff->id, ...$args))
        ->toThrow(ModelNotFoundException::class);

    expect($this->lechStaff->fresh())
        ->is_active->toBeTrue()
        ->role->value->toBe('staff');
})->with([
    'change role' => ['changeRole', ['owner']],
    'deactivate' => ['deactivate', []],
    'resend invitation' => ['resendInvite', []],
]);

it('puts new records into the current school automatically', function () {
    app(CurrentSchool::class)->set($this->lech);

    $user = User::factory()->make(['school_id' => null]);
    $user->save();

    expect($user->school_id)->toBe($this->lech->id);
});
