<?php

/**
 * Application Web Routes
 *
 * Registers all HTTP routes for the SMART application, including role-based dashboard redirection,
 * private media serving, real-time notification endpoints, inventory management CRUD, requisition/borrow carts,
 * manager approval workflows, barcode scanner routes, and local environment error page previews.
 */

use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\ManajemenStokController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\AdminApprovedRequestController;
use App\Http\Controllers\Smart\Admin\AdminRequestConfirmationController;
use App\Http\Controllers\Smart\Admin\HandoverController;
use App\Http\Controllers\Smart\Admin\BorrowedController;
use App\Http\Controllers\Smart\Admin\ReturnController;
use App\Http\Controllers\Smart\User\UserDashboardController;
use App\Http\Controllers\Smart\User\BrowseController;
use App\Http\Controllers\Smart\User\RequestCartController;
use App\Http\Controllers\Smart\User\BorrowCartController;
use App\Http\Controllers\Smart\User\RequestCartConfirmationController;
use App\Http\Controllers\Smart\User\BorrowCartConfirmationController;
use App\Http\Controllers\Smart\User\RequestHistoryController;
use App\Http\Controllers\Smart\User\RequestCancellationController;
use App\Http\Controllers\Smart\Manager\ManagerRequestController;
use App\Http\Controllers\Smart\Manager\ManagerApprovedRequestController;
use App\Http\Controllers\Smart\Manager\ManagerRequestApprovalController;
use App\Http\Controllers\Smart\Manager\ExternalApprovalController;
use App\Http\Controllers\Smart\Admin\AuditController;
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



// Secure media route for private storage
Route::middleware(['auth'])->group(function () {
    Route::get('/media/{path}', [\App\Http\Controllers\MediaController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');
});

// External Signed Manager Approval Routes (Zero-login, HMAC-protected)
Route::prefix('smart')->name('smart.external-approval.')->middleware(['signed'])->group(function () {
    Route::get('/external-approval/{request}', [ExternalApprovalController::class, 'show'])->name('show');
    Route::post('/external-approval/{request}', [ExternalApprovalController::class, 'store'])->name('action');
});

// Smart routes - protected
Route::middleware(['auth'])->prefix('smart')->name('smart.')->group(function () {
    // Notification routes (for all authenticated users)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Smart\NotificationController::class, 'index'])->name('index');
        Route::get('/mercure-token', [\App\Http\Controllers\Smart\NotificationController::class, 'token'])->name('mercure-token');
        Route::post('/{id}/read', [\App\Http\Controllers\Smart\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/{id}/unread', [\App\Http\Controllers\Smart\NotificationController::class, 'markAsUnread'])->name('unread');
        Route::post('/read-all', [\App\Http\Controllers\Smart\NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/clear', [\App\Http\Controllers\Smart\NotificationController::class, 'clearRead'])->name('clear');
        Route::delete('/{id}', [\App\Http\Controllers\Smart\NotificationController::class, 'destroy'])->name('destroy');
    });

    // Admin only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/scan', [\App\Http\Controllers\Smart\MultiRoles\ScanBarcodeController::class, 'show'])->name('scan-barcode');
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
            Route::post('units/{unit}/borrow', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitBorrowController::class, 'borrow'])->name('units.borrow');
            Route::post('units/{unit}/finish-borrow', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitBorrowController::class, 'finish'])->name('units.finish-borrow');
            Route::get('users', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitBorrowController::class, 'users'])->name('users');
            Route::resource('units', \App\Http\Controllers\Smart\Admin\ManajemenStok\UnitController::class)->only(['store', 'update', 'destroy']);
            Route::get('units/{unit}/qr-code', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitQrCodeController::class, 'show'])->name('units.qr-code');
            Route::resource('unit-status-approvals', \App\Http\Controllers\Smart\MultiRoles\UnitStatusApproval\AdminUnitStatusApprovalController::class)->only(['store']);
            Route::get('assets', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitController::class, 'index'])->name('assets');
            Route::get('pending-nonaktif', [\App\Http\Controllers\Smart\Admin\ManajemenStok\PendingNonaktifController::class, 'index'])->name('pending-nonaktif');
            Route::get('stok-habis-pakai/{barang?}', [\App\Http\Controllers\Smart\Admin\ManajemenStok\ConsumableLotController::class, 'index'])->name('stok-habis-pakai');
        });

        Route::get('scan/{unit}', [\App\Http\Controllers\Smart\Admin\ManajemenStok\UnitScanController::class, 'show'])->name('scan');

        Route::get('/inventory/{barang}', [ManajemenStokController::class, 'show'])->name('inventory.show');

        Route::get('/inbox', [AdminApprovedRequestController::class, 'index'])->name('inbox');
        Route::get('/inbox/{id}', [AdminApprovedRequestController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/confirmation', [AdminRequestConfirmationController::class, 'store'])->name('inbox.confirmation');
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
        Route::get('/audit', [AuditController::class, 'index'])->name('audit');
    });

    // Manager only routes
    Route::middleware(['role:manager'])->group(function () {
        Route::get('/approve', [ManagerRequestController::class, 'index'])->name('approve');
        Route::post('/approve/action', [ManagerRequestApprovalController::class, 'store'])->name('approve.bulk-action');
        Route::get('/approved', [ManagerApprovedRequestController::class, 'index'])->name('approved');

        // Asset Status Approval Routes
        Route::get('/approve-status', [\App\Http\Controllers\Smart\MultiRoles\UnitStatusApproval\ManagerUnitStatusApprovalController::class, 'index'])->name('approve-status');
        Route::post('/approve-status/bulk', [\App\Http\Controllers\Smart\MultiRoles\UnitStatusApproval\ManagerBulkUnitStatusApprovalController::class, 'store'])->name('approve-status.bulk-store');
    });

    // Manager and User routes
    Route::middleware(['role:manager,user'])->group(function () {
        Route::get('/user/dashboard', [UserDashboardController::class, 'index'])->name('user.dashboard');
        Route::get('/browse', [BrowseController::class, 'index'])->name('browse');

        Route::get('/asset-cart', [RequestCartController::class, 'index'])->name('asset-cart');
        Route::post('/asset-cart', [RequestCartController::class, 'store'])->name('asset-cart.store');
        Route::put('/asset-cart/{id}', [RequestCartController::class, 'update'])->name('asset-cart.update');
        Route::delete('/asset-cart/{id}', [RequestCartController::class, 'destroy'])->name('asset-cart.destroy');
        Route::get('/asset-cart/confirmation', [RequestCartConfirmationController::class, 'create'])->name('asset-cart.confirmation');
        Route::post('/asset-cart/confirmation', [RequestCartConfirmationController::class, 'store'])->name('asset-cart.confirmation.store');

        Route::get('/borrow-cart', [BorrowCartController::class, 'index'])->name('borrow-cart');
        Route::post('/borrow-cart', [BorrowCartController::class, 'store'])->name('borrow-cart.store');
        Route::put('/borrow-cart/{id}', [BorrowCartController::class, 'update'])->name('borrow-cart.update');
        Route::delete('/borrow-cart/{id}', [BorrowCartController::class, 'destroy'])->name('borrow-cart.destroy');
        Route::get('/borrow-cart/confirmation', [BorrowCartConfirmationController::class, 'create'])->name('borrow-cart.confirmation');
        Route::post('/borrow-cart/confirmation', [BorrowCartConfirmationController::class, 'store'])->name('borrow-cart.confirmation.store');
        Route::get('/history', [RequestHistoryController::class, 'index'])->name('history');
        Route::get('/history/{request:uuid}', [RequestHistoryController::class, 'show'])->name('history.show');
        Route::post('/history/{request:uuid}/cancel', [RequestCancellationController::class, 'store'])->name('history.cancel');
    });
});



// Error Page Preview Routes (for testing and design inspection in local/debug environments)
if (app()->environment('local', 'testing') || config('app.debug', false)) {
    Route::get('/errors/{code}', function ($code) {
        $validCodes = ['401', '403', '404', '419', '429', '500', '503'];
        if (in_array((string) $code, $validCodes, true)) {
            return response()->view("errors.{$code}", ['exception' => null], (int) $code);
        }
        abort(404);
    })->name('errors.preview');
}

require __DIR__.'/auth.php';

// Fallback - redirect unknown routes to root (which handles role-based redirection)
Route::fallback(function () {
    return redirect('/');
});
