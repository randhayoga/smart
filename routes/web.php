<?php


use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\ManajemenStokController;
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

// Root redirect - go to dashboard based on role
Route::get('/', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if (!$user) {
        return redirect()->route('login');
    }
    if ($user->is_admin) {
        return redirect()->route('smart.dashboard');
    }
    return redirect()->route('smart.user.dashboard');
});



// Smart routes - protected
Route::middleware(['auth'])->prefix('smart')->name('smart.')->group(function () {
    Route::post('/placement/update', [RequestHistoryController::class, 'updatePlacement'])->name('placement.update');

    // Admin only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/inventory', [ManajemenStokController::class, 'index'])->name('inventory');
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
            Route::put('barangs/bulk', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkBarangController::class, 'update'])->name('barangs.bulk-update');
            Route::delete('barangs/bulk', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkBarangController::class, 'destroy'])->name('barangs.bulk-destroy');
            Route::resource('barangs', \App\Http\Controllers\Smart\Admin\ManajemenStok\BarangController::class)->only(['store', 'update', 'destroy']);
            Route::put('lots/bulk', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkLotController::class, 'update'])->name('lots.bulk-update');
            Route::delete('lots/bulk', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkLotController::class, 'destroy'])->name('lots.bulk-destroy');
            Route::resource('lots', \App\Http\Controllers\Smart\Admin\ManajemenStok\LotController::class)->only(['store', 'update', 'destroy', 'show']);
            Route::post('units/bulk-update', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkUnitController::class, 'update'])->name('units.bulk-update');
            Route::post('units/bulk', [\App\Http\Controllers\Smart\Admin\ManajemenStok\BulkUnitController::class, 'store'])->name('units.bulk-store');
            Route::resource('units', \App\Http\Controllers\Smart\Admin\ManajemenStok\UnitController::class)->only(['store', 'update', 'destroy']);
            Route::resource('unit-status-approvals', \App\Http\Controllers\MultiRoles\UnitStatusApproval\AdminUnitStatusApprovalController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
        });

        Route::get('/inventory/{id}', [ManajemenStokController::class, 'show'])->name('inventory.show');

        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('/inbox/{id}', [InboxController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/{id}/action', [InboxController::class, 'action'])->name('inbox.action');
        Route::get('/handover', [HandoverController::class, 'index'])->name('handover');
        Route::get('/handover/{id}', [HandoverController::class, 'show'])->name('handover.show');
        Route::post('/handover/{id}/allocate', [HandoverController::class, 'allocate'])->name('handover.allocate');
        Route::get('/borrowed', [BorrowedController::class, 'index'])->name('borrowed');
        Route::get('/borrowed/{id}', [BorrowedController::class, 'show'])->name('borrowed.show');
        Route::get('/returns', [ReturnController::class, 'index'])->name('returns');
        Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('returns.show');
        Route::post('/returns/{id}/confirm', [ReturnController::class, 'confirm'])->name('returns.confirm');
        Route::get('/arsip', [\App\Http\Controllers\Smart\Admin\ArsipController::class, 'index'])->name('arsip');
        Route::get('/arsip/{id}', [\App\Http\Controllers\Smart\Admin\ArsipController::class, 'show'])->name('arsip.show');
    });

    // Manager only routes
    Route::middleware(['role:manager'])->group(function () {
        Route::get('/approve', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'index'])->name('approve');
        Route::get('/approve/{id}', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'show'])->name('approve.show');
        Route::post('/approve/{id}/action', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'action'])->name('approve.action');
        Route::post('/approve/action', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'bulkAction'])->name('approve.bulk-action');
        Route::get('/approved', [App\Http\Controllers\Smart\Manager\ApprovalController::class, 'approvedList'])->name('approved');

        // Asset Status Approval Routes
        Route::get('/approve-status', [\App\Http\Controllers\MultiRoles\UnitStatusApproval\ManagerUnitStatusApprovalController::class, 'index'])->name('approve-status');
        Route::post('/approve-status/bulk', [\App\Http\Controllers\MultiRoles\UnitStatusApproval\ManagerBulkUnitStatusApprovalController::class, 'store'])->name('approve-status.bulk-store');
    });

    // Manager and User routes
    Route::middleware(['role:manager,user'])->group(function () {
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
    });
});



require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to root (which handles role-based redirection)
Route::fallback(function () {
    return redirect('/');
});
