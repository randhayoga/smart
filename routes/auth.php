<?php

/**
 * Authentication Web Routes
 *
 * Defines guest and authenticated session routes including login submission and logout endpoints.
 */

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->prefix('auth')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated Session Routes
Route::middleware('auth')->prefix('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
