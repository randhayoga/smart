<?php

/**
 * Authentication Web Routes
 *
 * Automatically branches between Portal SSO (in production) and standard local authentication (in development).
 */

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

// Login routes (SSO in production, Vue form in development)
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/login', [AuthController::class, 'login']);

// Dev local POST login (used for development form submission)
Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store']);
});

// Logout routes (Portal redirect in production, root redirect in development)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
