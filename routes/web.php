<?php


use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\InventoryController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\InboxController;
use App\Http\Controllers\Smart\Admin\HandoverController;
use App\Http\Controllers\Smart\Admin\BorrowedController;
use App\Http\Controllers\Smart\Admin\ReturnController;
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
    Route::get('/inbox/{id}', [InboxController::class, 'show'])->name('inbox.show');
    Route::get('/handover', [HandoverController::class, 'index'])->name('handover');
    Route::get('/handover/{id}', [HandoverController::class, 'show'])->name('handover.show');
    Route::get('/borrowed', [BorrowedController::class, 'index'])->name('borrowed');
    Route::get('/borrowed/{id}', [BorrowedController::class, 'show'])->name('borrowed.show');
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns');
    Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('returns.show');
});



require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to dashboard
Route::fallback(function () {
    return redirect()->route('smart.dashboard');
});
