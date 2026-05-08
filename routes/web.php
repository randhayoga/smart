<?php


use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\InventoryController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\InboxController;
use App\Http\Controllers\Smart\User\UserDashboardController;
use Illuminate\Support\Facades\Route;

// Root redirect - always go to dashboard (middleware will handle auth)
Route::get('/', function () {
    return redirect()->route('smart.dashboard');
});



// Smart routes - protected
Route::middleware(['auth'])->prefix('smart')->name('smart.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/master', [MasterController::class, 'index'])->name('master');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
});



require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to dashboard
Route::fallback(function () {
    return redirect()->route('smart.dashboard');
});
