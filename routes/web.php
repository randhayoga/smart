<?php


use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\InventoryController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\InboxController;
use App\Http\Controllers\Smart\Admin\HandoverController;
use App\Http\Controllers\Smart\User\UserDashboardController;
use App\Http\Controllers\Smart\Admin\Master\CategoryController;
use App\Http\Controllers\Smart\Admin\Master\SubcategoryController;
use App\Http\Controllers\Smart\Admin\Master\UomController;
use App\Http\Controllers\Smart\Admin\Master\BrandController;
use App\Http\Controllers\Smart\Admin\Master\OrganizerController;
use App\Http\Controllers\Smart\Admin\Master\VendorController;
use App\Http\Controllers\Smart\Admin\Master\LocationController;
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

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('categories',    CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::resource('subcategories', SubcategoryController::class)->only(['store', 'update', 'destroy']);
        Route::resource('uoms',          UomController::class)->only(['store', 'update', 'destroy']);
        Route::resource('brands',        BrandController::class)->only(['store', 'update', 'destroy']);
        Route::resource('organizers',    OrganizerController::class)->only(['store', 'update', 'destroy']);
        Route::resource('vendors',       VendorController::class)->only(['store', 'update', 'destroy']);
        Route::resource('locations',     LocationController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/handover', [HandoverController::class, 'index'])->name('handover');
    Route::get('/handover/{id}', [HandoverController::class, 'show'])->name('handover.show');
});



require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to dashboard
Route::fallback(function () {
    return redirect()->route('smart.dashboard');
});
