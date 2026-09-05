<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WorkoutController;
use Illuminate\Support\Facades\Route;

// ── Authentication ────────────────────────────────────────────────────────────
// POST /api/auth/token  { email, password, device_name }  → { token }
// DELETE /api/auth/token  (revoke current token)
Route::prefix('auth')->group(function () {
    Route::post('/token', [AuthController::class, 'issue']);
    Route::delete('/token', [AuthController::class, 'revoke'])->middleware('auth:sanctum');
});

// ── Workout (Apple Watch sync) ────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('workouts')->group(function () {
    // POST   /api/workouts          log a completed workout
    // GET    /api/workouts          list recent workouts for this user
    Route::post('/', [WorkoutController::class, 'store']);
    Route::get('/', [WorkoutController::class, 'index']);
});
