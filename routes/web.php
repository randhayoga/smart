<?php

/**
 * Application Web Routes
 *
 * Registers all HTTP routes for the SMART application, including role-based dashboard redirection,
 * private media serving, real-time notification endpoints, inventory management CRUD, requisition/borrow carts,
 * manager approval workflows, barcode scanner routes, and local environment error page previews.
 */

use App\Http\Controllers\Smart\Admin\DashboardController;
use App\Http\Controllers\Smart\Admin\StockManagementController;
use App\Http\Controllers\Smart\Admin\MasterController;
use App\Http\Controllers\Smart\Admin\AdminApprovedRequestController;
use App\Http\Controllers\Smart\Admin\AdminRequestConfirmationController;
use App\Http\Controllers\Smart\Admin\AdminActiveRequestController;
use App\Http\Controllers\Smart\Admin\AdminConfirmedRequestController;
use App\Http\Controllers\Smart\Admin\AdminPartialRequestController;
use App\Http\Controllers\Smart\Admin\AdminRequestFulfillmentController;
use App\Http\Controllers\Smart\Admin\RequestItemAssetAssignmentController;
use App\Http\Controllers\Smart\Admin\RequestItemLotAssignmentController;
use App\Http\Controllers\Smart\Admin\RequestFulfillmentConfirmationController;
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
use App\Http\Controllers\Smart\Admin\AuditTrailController;
use App\Http\Controllers\Smart\Admin\ArchiveController;
use App\Http\Controllers\Smart\Admin\InventoryAuditController;
use App\Http\Controllers\Smart\Admin\Master\CategoryController;
use App\Http\Controllers\Smart\Admin\Master\TypeController;
use App\Http\Controllers\Smart\Admin\Master\UomController;
use App\Http\Controllers\Smart\Admin\Master\BrandController;
use App\Http\Controllers\Smart\Admin\Master\OrganizerController;
use App\Http\Controllers\Smart\Admin\Master\VendorController;
use App\Http\Controllers\Smart\Admin\Master\LocationController;
use App\Http\Controllers\Smart\Admin\StockManagement\AssetController;
use App\Http\Controllers\Smart\Admin\StockManagement\BulkAssetController;
use App\Http\Controllers\Smart\Admin\StockManagement\AssetBorrowController;
use App\Http\Controllers\Smart\Admin\StockManagement\AssetScanController;
use App\Http\Controllers\Smart\Admin\StockManagement\AssetQrCodeController;
use App\Http\Controllers\Smart\Admin\StockManagement\ModelController;
use App\Http\Controllers\Smart\Admin\StockManagement\BulkModelController;
use App\Http\Controllers\Smart\Admin\StockManagement\ModelManualRequestController;
use App\Http\Controllers\Smart\Admin\StockManagement\ConsumableController;
use App\Http\Controllers\Smart\Admin\StockManagement\NonConsumableController;
use App\Http\Controllers\Smart\Admin\StockManagement\LotController;
use App\Http\Controllers\Smart\Admin\StockManagement\BulkLotController;
use App\Http\Controllers\Smart\Admin\StockManagement\LotManualRequestController;
use App\Http\Controllers\Smart\Admin\StockManagement\ConsumableRequestOptionController;
use App\Http\Controllers\Smart\Admin\StockManagement\PendingDeactivationController;
use App\Http\Controllers\Smart\Admin\StockManagement\PendingActivationController;
use App\Http\Controllers\Smart\Admin\StockManagement\NewStockController;
use App\Http\Controllers\Smart\Admin\Employees\EmployeeController;
use App\Http\Controllers\Smart\Admin\Employees\EmployeeLoanController;
use App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval\AdminAssetDeactivationApprovalController;
use App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval\ManagerAssetDeactivationApprovalController;
use App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval\ManagerBulkAssetDeactivationApprovalController;
use App\Http\Controllers\Smart\MultiRoles\AssetActivationApproval\ManagerAssetActivationApprovalController;
use App\Http\Controllers\Smart\MultiRoles\AssetActivationApproval\ManagerBulkAssetActivationApprovalController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// Locale preference switching
Route::match(['put', 'post'], '/locale', [LocaleController::class, 'update'])->name('locale.update');

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
    // Notification routes (for all authenticated users with notifications.manage permission)
    Route::prefix('notifications')->name('notifications.')->middleware(['permission:notifications.manage'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Smart\NotificationController::class, 'index'])->name('index');
        Route::get('/mercure-token', [\App\Http\Controllers\Smart\NotificationController::class, 'token'])->name('mercure-token');
        Route::post('/{id}/read', [\App\Http\Controllers\Smart\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/{id}/unread', [\App\Http\Controllers\Smart\NotificationController::class, 'markAsUnread'])->name('unread');
        Route::post('/read-all', [\App\Http\Controllers\Smart\NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/clear', [\App\Http\Controllers\Smart\NotificationController::class, 'clearRead'])->name('clear');
        Route::delete('/{id}', [\App\Http\Controllers\Smart\NotificationController::class, 'destroy'])->name('destroy');
    });

    // Superadmin only routes (Access Management)
    Route::middleware(['permission:access.manage'])->prefix('access')->name('access.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Smart\Admin\AccessManagement\AccessManagementController::class, 'index'])->name('index');
        Route::put('/users/{user}/role', [\App\Http\Controllers\Smart\Admin\AccessManagement\UserRoleController::class, 'update'])->name('users.role.update');
        Route::post('/sync-managers', [\App\Http\Controllers\Smart\Admin\AccessManagement\SyncUserHrisManagersController::class, 'store'])->name('sync-managers');
        Route::post('/roles', [\App\Http\Controllers\Smart\Admin\AccessManagement\RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [\App\Http\Controllers\Smart\Admin\AccessManagement\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [\App\Http\Controllers\Smart\Admin\AccessManagement\RoleController::class, 'destroy'])->name('roles.destroy');
        Route::put('/roles/{role}/permissions', [\App\Http\Controllers\Smart\Admin\AccessManagement\RolePermissionController::class, 'update'])->name('roles.permissions.update');
    });

    // Admin and Management Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware(['permission:dashboard.admin.view'])
        ->name('dashboard');

    // Master Data Management
    Route::get('/master', [MasterController::class, 'index'])
        ->middleware(['permission:master.view'])
        ->name('master');

    Route::prefix('master')->name('master.')->middleware(['permission:master.manage'])->group(function () {
        Route::resource('categories',    CategoryController::class)->only(['store', 'update', 'destroy']);
        Route::resource('types',         TypeController::class)->only(['store', 'update', 'destroy']);
        Route::resource('uoms',          UomController::class)->only(['store', 'update', 'destroy']);
        Route::resource('brands',        BrandController::class)->only(['store', 'update', 'destroy']);
        Route::resource('organizers',    OrganizerController::class)->only(['store', 'update', 'destroy']);
        Route::resource('vendors',       VendorController::class)->only(['store', 'update', 'destroy']);
        Route::patch('locations/{location}/toggle-active', [LocationController::class, 'toggleActive'])->name('locations.toggle-active');
        Route::resource('locations',     LocationController::class)->only(['store', 'update', 'destroy']);
    });

    // Barcode Scanning
    Route::get('/scan', [\App\Http\Controllers\Smart\MultiRoles\ScanBarcodeController::class, 'show'])
        ->middleware(['permission:inventory.manage,inventory.borrow'])
        ->name('scan-barcode');
    Route::get('scan/{asset}', [AssetScanController::class, 'show'])
        ->middleware(['permission:inventory.view'])
        ->name('scan');

    // Inventory Catalogs and Management
    Route::get('/stock-management', [StockManagementController::class, 'index'])
        ->middleware(['permission:inventory.manage'])
        ->name('inventory');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        // Read-only Inventory Views (Assets, Consumable Lots, Asset QR)
        Route::middleware(['permission:inventory.view'])->group(function () {
            Route::get('non-consumables', [NonConsumableController::class, 'index'])->name('non-consumables');
            Route::get('consumables/request-options', [ConsumableRequestOptionController::class, 'index'])->name('consumables.request-options');
            Route::get('consumables/{model?}', [ConsumableController::class, 'index'])->name('consumables');
            Route::get('lots/{lot}', [LotController::class, 'show'])->name('lots.show');
            Route::get('assets/{asset}/qr-code', [AssetQrCodeController::class, 'show'])->name('assets.qr-code');
        });

        // Models, Lots, Assets Management (CRUD & Bulk)
        Route::middleware(['permission:inventory.manage'])->group(function () {
            // Models
            Route::put('models/bulk', [BulkModelController::class, 'update'])->name('models.bulk-update');
            Route::delete('models/bulk', [BulkModelController::class, 'destroy'])->name('models.bulk-destroy');
            Route::resource('models', ModelController::class)->only(['store', 'update', 'destroy']);

            // Lots
            Route::put('lots/bulk', [BulkLotController::class, 'update'])->name('lots.bulk-update');
            Route::delete('lots/bulk', [BulkLotController::class, 'destroy'])->name('lots.bulk-destroy');
            Route::resource('lots', LotController::class)->only(['store', 'update', 'destroy']);

            // Assets
            Route::post('assets/bulk-update', [BulkAssetController::class, 'update'])->name('assets.bulk-update');
            Route::post('assets/bulk', [BulkAssetController::class, 'store'])->name('assets.bulk-store');
            Route::resource('assets', AssetController::class)->only(['store', 'update']);

            // New Stock Unified Input
            Route::post('new-stock', [NewStockController::class, 'store'])->name('new-stock.store');
        });

        // Direct Borrowing Actions
        Route::middleware(['permission:inventory.borrow'])->group(function () {
            Route::post('assets/{asset}/borrow', [AssetBorrowController::class, 'borrow'])->name('assets.borrow');
            Route::post('assets/{asset}/finish-borrow', [AssetBorrowController::class, 'finish'])->name('assets.finish-borrow');
            Route::get('users', [AssetBorrowController::class, 'users'])->name('users');
        });

        // Manual Stock Request Actions
        Route::middleware(['permission:inventory.manual_request'])->group(function () {
            Route::post('models/{model}/manual-request', [ModelManualRequestController::class, 'store'])->name('models.manual-request');
            Route::post('lots/{lot}/manual-request', [LotManualRequestController::class, 'store'])->name('lots.manual-request');
            Route::get('request-options', [ConsumableRequestOptionController::class, 'index'])->name('request-options');
        });

        // Asset Deactivation Submissions
        Route::middleware(['permission:inventory.status_approval.request'])->group(function () {
            Route::resource('asset-deactivation-approvals', AdminAssetDeactivationApprovalController::class)->only(['store']);
            Route::get('pending-deactivation', [PendingDeactivationController::class, 'index'])->name('pending-deactivation');
            Route::get('pending-activation', [PendingActivationController::class, 'index'])->name('pending-activation');
        });
    });

    Route::get('/inventory/{model}', [StockManagementController::class, 'show'])
        ->middleware(['permission:inventory.view'])
        ->name('inventory.show');

    // Asset Status Decisions
    Route::middleware(['permission:inventory.status_approval.decide'])->group(function () {
        Route::get('/approve-deactivation', [ManagerAssetDeactivationApprovalController::class, 'index'])->name('approve-deactivation');
        Route::post('/approve-deactivation/bulk', [ManagerBulkAssetDeactivationApprovalController::class, 'store'])->name('approve-deactivation.bulk-store');
        Route::get('/approve-activation', [ManagerAssetActivationApprovalController::class, 'index'])->name('approve-activation');
        Route::post('/approve-activation/bulk', [ManagerBulkAssetActivationApprovalController::class, 'store'])->name('approve-activation.bulk-store');
    });

    // Employee Directory & Loan History
    Route::middleware(['permission:karyawan.view'])->group(function () {
        Route::get('/employee-list', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employee-list/{employee}/loans', [EmployeeLoanController::class, 'index'])->name('employees.loans');
    });

    // Audit Trails
    Route::middleware(['permission:audit.view'])->group(function () {
        Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit');
        Route::get('/inventory-audit', [InventoryAuditController::class, 'index'])->name('inventory-audit');
    });

    // Active Requisitions Management
    Route::middleware(['permission:requests.inbox.view'])->group(function () {
        Route::get('/requests', [AdminActiveRequestController::class, 'index'])->name('requests.index');
        Route::get('/inbox', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
            if ($request->wantsJson()) {
                return app(AdminApprovedRequestController::class)->index($request, $stockService);
            }
            $request->merge(['tab' => 'Inbox']);
            return app(AdminActiveRequestController::class)->index($request, $stockService);
        })->name('inbox');
        Route::get('/inbox/{id}', [AdminApprovedRequestController::class, 'show'])->name('inbox.show');
    });

    Route::post('/inbox/confirmation', [AdminRequestConfirmationController::class, 'store'])
        ->middleware(['permission:requests.confirm'])
        ->name('inbox.confirmation');

    // Request Fulfillment (Asset Assignment & Lot Allocation)
    Route::prefix('fulfillment')->name('fulfillment.')->middleware(['permission:requests.confirm,requests.fulfill'])->group(function () {
        Route::get('/', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
            if ($request->wantsJson()) {
                return app(AdminConfirmedRequestController::class)->index($request);
            }
            $request->merge(['tab' => 'Perlu Alokasi']);
            return app(AdminActiveRequestController::class)->index($request, $stockService);
        })->name('index');
        Route::get('/{id}', [AdminRequestFulfillmentController::class, 'show'])->name('show');
        Route::post('/items/{item}/assign', [RequestItemAssetAssignmentController::class, 'store'])->name('items.assign');
        Route::post('/items/{item}/assign-lots', [RequestItemLotAssignmentController::class, 'store'])->name('items.assign-lots');
        Route::post('/{id}/confirm', [RequestFulfillmentConfirmationController::class, 'store'])->name('confirm');
    });

    // Partially Fulfilled Requests Page
    Route::get('/partial', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
        if ($request->wantsJson()) {
            return app(AdminPartialRequestController::class)->index($request);
        }
        $request->merge(['tab' => 'Parsial']);
        return app(AdminActiveRequestController::class)->index($request, $stockService);
    })->middleware(['permission:requests.confirm,requests.fulfill'])->name('partial.index');

    // Handover Management
    Route::middleware(['permission:requests.handover'])->group(function () {
        Route::get('/handover', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
            if ($request->wantsJson()) {
                return app(HandoverController::class)->index();
            }
            $request->merge(['tab' => 'Serah Terima']);
            return app(AdminActiveRequestController::class)->index($request, $stockService);
        })->name('handover');
        Route::get('/handover/{id}', [HandoverController::class, 'show'])->name('handover.show');
        Route::post('/handover/{id}/allocate', [HandoverController::class, 'allocate'])->name('handover.allocate');
    });

    // Loan Tracking & Returns
    Route::middleware(['permission:requests.returns,requests.inbox.view'])->group(function () {
        Route::get('/borrowed', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
            if ($request->wantsJson()) {
                return app(BorrowedController::class)->index();
            }
            $request->merge(['tab' => 'Lacak Peminjaman']);
            return app(AdminActiveRequestController::class)->index($request, $stockService);
        })->name('borrowed');
        Route::get('/borrowed/{id}', [BorrowedController::class, 'show'])->name('borrowed.show');
    });

    Route::middleware(['permission:requests.returns'])->group(function () {
        Route::get('/returns', function (\Illuminate\Http\Request $request, \App\Services\InventoryStockService $stockService) {
            if ($request->wantsJson()) {
                return app(ReturnController::class)->index();
            }
            $request->merge(['tab' => 'Pengembalian']);
            return app(AdminActiveRequestController::class)->index($request, $stockService);
        })->name('returns');
        Route::get('/returns/{id}', [ReturnController::class, 'show'])->name('returns.show');
        Route::post('/returns/{id}/confirm', [ReturnController::class, 'confirm'])->name('returns.confirm');
    });

    // Requisition Archive
    Route::middleware(['permission:requests.archive.view'])->group(function () {
        Route::get('/archive', [ArchiveController::class, 'index'])->name('archive');
        Route::get('/archive/{id}', [ArchiveController::class, 'show'])->name('archive.show');
    });

    // Manager Approvals
    Route::middleware(['permission:requests.approve'])->group(function () {
        Route::get('/request-approve', [ManagerRequestController::class, 'index'])->name('approve');
        Route::post('/request-approve/action', [ManagerRequestApprovalController::class, 'store'])->name('approve.bulk-action');
        Route::get('/request-approved', [ManagerApprovedRequestController::class, 'index'])->name('approved');
    });

    // User Requisition & History
    Route::get('/user/dashboard', [UserDashboardController::class, 'index'])
        ->middleware(['permission:dashboard.user.view'])
        ->name('user.dashboard');

    Route::middleware(['permission:requests.create'])->group(function () {
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
    });

    Route::middleware(['permission:requests.view_own'])->group(function () {
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
