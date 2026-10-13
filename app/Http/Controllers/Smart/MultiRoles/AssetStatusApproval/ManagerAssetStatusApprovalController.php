<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetStatusApproval;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerAssetStatusApprovalResource;
use App\Models\Inventory\AssetStatusApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Asset Status Approval Controller displaying pending and historical asset status change reviews.
 */
class ManagerAssetStatusApprovalController extends Controller
{
    /**
     * Display a listing of asset status approvals for managers (pending or history).
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $query = AssetStatusApproval::with([
            'asset.lot.model.type.category',
            'asset.lot.model.brand',
            'asset.lot.model.uom',
            'asset.lot.organizer',
            'asset.lot.vendor',
            'asset.lot.legacyVendor',
            'asset.location.parent',
            'requester',
            'approver'
        ]);

        if ($request->boolean('history')) {
            $query->where('decision', '!=', 'pending');
            $view = 'Smart/Manager/SudahApproveStatus';
        } else {
            $query->where('decision', 'pending')
                  ->whereHas('asset', function ($q) {
                      $q->where('status', 'Pending:DM');
                  });
            $view = 'Smart/Manager/PerluApproveStatus';
        }

        $approvals = $query->orderBy('id', 'desc')->get();

        return Inertia::render($view, [
            'user' => $request->user(),
            'approvals' => ManagerAssetStatusApprovalResource::collection($approvals)->resolve(),
        ]);
    }
}
