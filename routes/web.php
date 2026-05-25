<?php


use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\InventoryController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\InboxController;
use App\Http\Controllers\Smart\Admin\HandoverController;
use App\Http\Controllers\Smart\Admin\BorrowedController;
use App\Http\Controllers\Smart\Admin\ReturnController;
use App\Http\Controllers\Smart\User\UserDashboardController;
use App\Http\Controllers\Smart\User\BrowseController;
use App\Http\Controllers\Smart\User\AssetCartController;
use App\Http\Controllers\Smart\User\BorrowCartController;
use App\Http\Controllers\Smart\User\AssetCartConfirmationController;
use App\Http\Controllers\Smart\User\BorrowCartConfirmationController;
use App\Http\Controllers\Smart\User\RequestHistoryController;
use App\Http\Controllers\Smart\Admin\Master\CategoryController;
use App\Http\Controllers\Smart\Admin\Master\SubcategoryController;
use App\Http\Controllers\Smart\Admin\Master\UomController;
use App\Http\Controllers\Smart\Admin\Master\BrandController;
use App\Http\Controllers\Smart\Admin\Master\OrganizerController;
use App\Http\Controllers\Smart\Admin\Master\VendorController;
use App\Http\Controllers\Smart\Admin\Master\LocationController;
use App\Http\Controllers\Smart\Admin\Master\FloorController;
use App\Http\Controllers\Smart\Admin\Master\RoomController;
use Illuminate\Support\Facades\Route;

// Root redirect - always go to dashboard (middleware will handle auth)
Route::get('/', function () {
    return redirect()->route('smart.dashboard');
});



// Smart routes - protected
Route::middleware(['auth'])->prefix('smart')->name('smart.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard');
    Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
    Route::post('/browse/add-to-cart', [BrowseController::class, 'addToCart'])->name('browse.add-to-cart');

    Route::get('/asset-cart', [AssetCartController::class, 'index'])->name('asset-cart');
    Route::put('/asset-cart/{id}', [AssetCartController::class, 'update'])->name('asset-cart.update');
    Route::delete('/asset-cart/{id}', [AssetCartController::class, 'destroy'])->name('asset-cart.destroy');
    Route::get('/asset-cart/confirmation', [AssetCartConfirmationController::class, 'index'])->name('asset-cart.confirmation');
    Route::post('/asset-cart/confirmation', [AssetCartConfirmationController::class, 'store'])->name('asset-cart.confirmation.store');

    Route::get('/borrow-cart', [BorrowCartController::class, 'index'])->name('borrow-cart');
    Route::put('/borrow-cart/{id}', [BorrowCartController::class, 'update'])->name('borrow-cart.update');
    Route::delete('/borrow-cart/{id}', [BorrowCartController::class, 'destroy'])->name('borrow-cart.destroy');
    Route::get('/borrow-cart/confirmation', [BorrowCartConfirmationController::class, 'index'])->name('borrow-cart.confirmation');
    Route::post('/borrow-cart/confirmation', [BorrowCartConfirmationController::class, 'store'])->name('borrow-cart.confirmation.store');
    Route::get('/history', [RequestHistoryController::class, 'index'])->name('history');
    Route::get('/history/{id}', [RequestHistoryController::class, 'show'])->name('history.show');
    Route::post('/history/{id}/cancel', [RequestHistoryController::class, 'cancel'])->name('history.cancel');
    Route::post('/history/{id}/handover', [RequestHistoryController::class, 'handover'])->name('history.handover');
    Route::post('/history/{id}/receive', [RequestHistoryController::class, 'receive'])->name('history.receive');
    Route::post('/history/{id}/return', [RequestHistoryController::class, 'returnAsset'])->name('history.return');

    // Manager approval routes
    Route::get('/approve', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'index'])->name('approve');
    Route::get('/approve/{id}', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'show'])->name('approve.show');
    Route::post('/approve/{id}/action', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'action'])->name('approve.action');
    Route::get('/approved', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'approvedList'])->name('approved');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory');
    Route::get('/inventory/{id}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::get('/master', [MasterController::class, 'index'])->name('master');

    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('categories',    CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::resource('subcategories', SubcategoryController::class)->only(['store', 'update', 'destroy']);
        Route::resource('uoms',          UomController::class)->only(['store', 'update', 'destroy']);
        Route::resource('brands',        BrandController::class)->only(['store', 'update', 'destroy']);
        Route::resource('organizers',    OrganizerController::class)->only(['store', 'update', 'destroy']);
        Route::resource('vendors',       VendorController::class)->only(['store', 'update', 'destroy']);
        Route::resource('locations',     LocationController::class)->only(['store', 'update', 'destroy']);
        Route::resource('floors',        FloorController::class)->only(['store', 'update', 'destroy']);
        Route::resource('rooms',         RoomController::class)->only(['store', 'update', 'destroy']);
    });

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::resource('barangs', \App\Http\Controllers\Smart\Admin\Inventory\BarangController::class)->only(['store', 'update', 'destroy']);
        Route::resource('lots', \App\Http\Controllers\Smart\Admin\Inventory\LotController::class)->only(['store', 'update', 'destroy']);
    });

    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/inbox/{id}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{id}/action', [InboxController::class, 'action'])->name('inbox.action');
    Route::get('/handover', [HandoverController::class, 'index'])->name('handover');
    Route::get('/handover/{id}', [HandoverController::class, 'show'])->name('handover.show');
    Route::get('/borrowed', [BorrowedController::class, 'index'])->name('borrowed');
    Route::get('/borrowed/{id}', [BorrowedController::class, 'show'])->name('borrowed.show');
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns');
    Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{id}/confirm', [ReturnController::class, 'confirm'])->name('returns.confirm');
    Route::get('/arsip', [\App\Http\Controllers\Smart\Admin\ArsipController::class, 'index'])->name('arsip');
    Route::get('/arsip/{id}', [\App\Http\Controllers\Smart\Admin\ArsipController::class, 'show'])->name('arsip.show');
});



require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to dashboard
Route::fallback(function () {
    return redirect()->route('smart.dashboard');
});
