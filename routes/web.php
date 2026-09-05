<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminClassController;
use App\Http\Controllers\Admin\AdminCoachController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Coach\CoachClassController;
use App\Http\Controllers\Coach\CoachDashboardController;
use App\Http\Controllers\MyBookingsController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\WodController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }
    $user = auth()->user();
    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }
    if ($user->isCoach()) {
        return redirect()->route('coach.dashboard');
    }

    return redirect()->route('schedule');
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }
    if ($user->isCoach()) {
        return redirect()->route('coach.dashboard');
    }

    return redirect()->route('schedule');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
    Route::get('/training', [TrainingController::class,    'index'])->name('training');
    Route::get('/results', [ResultsController::class, 'index'])->name('results');
    Route::post('/results', [ResultsController::class, 'store'])->name('results.store');
    Route::delete('/results/{workoutResult}', [ResultsController::class, 'destroy'])->name('results.destroy');
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities');
    Route::get('/my-bookings', [MyBookingsController::class, 'index'])->name('my-bookings');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings', [BookingController::class, 'destroy'])->name('bookings.destroy');

    // Packages
    Route::get('/packages', [PackageController::class, 'index'])->name('packages');
    Route::post('/packages/{package}/subscribe', [PackageController::class, 'subscribe'])->name('packages.subscribe');
});

// ── Coach Portal ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'coach'])
    ->prefix('coach')
    ->name('coach.')
    ->group(function () {
        Route::get('/', [CoachDashboardController::class, 'index'])->name('dashboard');
        Route::get('/classes', [CoachClassController::class, 'index'])->name('classes.index');
        Route::get('/classes/{gymClass}', [CoachClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{gymClass}/attendance', [CoachClassController::class, 'updateAttendance'])->name('classes.attendance');
    });

// ── Admin Panel ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/subscriptions', [AdminUserController::class, 'assignSubscription'])->name('users.subscriptions.store');
        Route::patch('/users/{user}/credits', [AdminUserController::class, 'adjustCredits'])->name('users.credits.update');
        Route::post('/users/{user}/bookings', [AdminUserController::class, 'bookForMember'])->name('users.bookings.store');
        Route::delete('/users/{user}/bookings/{booking}', [AdminUserController::class, 'cancelBookingForMember'])->name('users.bookings.destroy');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status.update');

        // Classes
        Route::get('/classes', [AdminClassController::class, 'index'])->name('classes.index');
        Route::post('/classes', [AdminClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/slot', [AdminClassController::class, 'slot'])->name('classes.slot');
        Route::post('/classes/generate', [AdminClassController::class, 'generate'])->name('classes.generate');
        Route::post('/classes/generate-wod', [AdminClassController::class, 'generateWod'])->name('classes.generate-wod');
        Route::patch('/classes/{gymClass}', [AdminClassController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{gymClass}', [AdminClassController::class, 'destroy'])->name('classes.destroy');

        // Class templates
        Route::post('/class-templates', [AdminClassController::class, 'storeTemplate'])->name('class-templates.store');
        Route::patch('/class-templates/{template}', [AdminClassController::class, 'updateTemplate'])->name('class-templates.update');
        Route::delete('/class-templates/{template}', [AdminClassController::class, 'destroyTemplate'])->name('class-templates.destroy');

        // Coaches
        Route::get('/coaches', [AdminCoachController::class, 'index'])->name('coaches.index');
        Route::post('/coaches', [AdminCoachController::class, 'store'])->name('coaches.store');
        Route::patch('/coaches/{user}', [AdminCoachController::class, 'update'])->name('coaches.update');
        Route::delete('/coaches/{user}', [AdminCoachController::class, 'destroy'])->name('coaches.destroy');

        // Bookings
        Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::delete('/bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');
        Route::patch('/bookings/{booking}/attendance', [AdminBookingController::class, 'updateAttendance'])->name('bookings.attendance');
        Route::post('/bookings/{booking}/promote', [AdminBookingController::class, 'promoteWaitlist'])->name('bookings.promote');

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

        // Packages
        Route::get('/packages', [AdminPackageController::class, 'index'])->name('packages.index');
        Route::post('/packages', [AdminPackageController::class, 'store'])->name('packages.store');
        Route::patch('/packages/{package}', [AdminPackageController::class, 'update'])->name('packages.update');
        Route::delete('/packages/{package}', [AdminPackageController::class, 'destroy'])->name('packages.destroy');
    });

// ── Public WOD page (passcode-gated, no login required) ──────────────────────
Route::get('/wod', [WodController::class, 'index'])->name('wod');
Route::post('/wod/verify', [WodController::class, 'verify'])->name('wod.verify');
Route::post('/wod/logout', [WodController::class, 'logout'])->name('wod.logout');

require __DIR__.'/auth.php';
