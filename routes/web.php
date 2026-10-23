<?php

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Livewire\Account;
use App\Livewire\Booking;
use App\Livewire\Bookings;
use App\Livewire\Instructors;
use App\Livewire\Settings;
use App\Livewire\Students;
use App\Livewire\Users;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
| Everything is back office only (US-27). There is no public page and no
| self-registration: accounts are created by invitation (US-31).
*/

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.store');

    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitation.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('account', Account::class)->name('account');

    // Booking (US-09 to US-13): back office only, staff and owners.
    Route::get('booking', Booking\Shop::class)->name('booking');
    Route::get('booking/checkout', Booking\Checkout::class)->name('booking.checkout');
    Route::get('bookings/{booking}', Bookings\Show::class)->name('bookings.show');

    // Students (US-17 to US-19): staff and owners.
    Route::get('students', Students\Index::class)->name('students.index');
    Route::get('students/new', Students\Edit::class)->name('students.create');
    Route::get('students/{student}', Students\Edit::class)->name('students.edit');

    // Instructors (US-20, US-21): everyone sees the list, owners edit.
    Route::get('instructors', Instructors\Index::class)->name('instructors.index');
    Route::middleware('can:admin')->group(function () {
        Route::get('instructors/new', Instructors\Edit::class)->name('instructors.create');
        Route::get('instructors/{instructor}/edit', Instructors\Edit::class)->name('instructors.edit');
    });

    // Owner only, enforced here and again inside the component.
    Route::get('users', Users\Index::class)
        ->middleware('can:viewAny,'.User::class)
        ->name('users.index');

    // Global school settings (build step 2): owner only (US-27).
    Route::prefix('settings')->name('settings.')->middleware('can:admin')->group(function () {
        Route::redirect('/', '/settings/general');
        Route::get('general', Settings\General::class)->name('general');
        Route::get('season', Settings\Season::class)->name('season');
        Route::get('times', Settings\Times::class)->name('times');
        Route::get('sports', Settings\Sports::class)->name('sports');
        Route::get('skill-levels', Settings\SkillLevels::class)->name('skill-levels');
        Route::get('age-groups', Settings\AgeGroups::class)->name('age-groups');
        Route::get('meeting-points', Settings\MeetingPoints::class)->name('meeting-points');
        Route::get('add-ons', Settings\AddOns::class)->name('add-ons');
        Route::get('vouchers', Settings\Vouchers::class)->name('vouchers');

        // Price tables (build step 3, US-07)
        Route::get('price-tables', Settings\PriceTables\Index::class)->name('price-tables');
        Route::get('price-tables/new', Settings\PriceTables\Edit::class)->name('price-tables.create');
        Route::get('price-tables/{priceTable}/edit', Settings\PriceTables\Edit::class)->name('price-tables.edit');
    });
});
