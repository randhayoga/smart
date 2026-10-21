<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerAssetStatusApprovalResource;
use App\Models\Inventory\AssetStatusApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Asset Deactivation Approval Controller displaying pending and historical asset deactivation reviews.
 */
class ManagerAssetDeactivationApprovalController extends Controller
{
    /**
     * Display a listing of asset deactivation approvals for managers (pending or history).
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
            'asset.location.parent.parent',
            'asset.lifecycles.actor',
            'requester',
            'approver'
        ]);

        if ($request->boolean('history')) {
            $query->where('decision', '!=', 'pending');
            $view = 'Smart/Manager/ProcessedDeactivationApprovals';
        } else {
            $query->where('decision', 'pending')
                  ->whereHas('asset', function ($q) {
                      $q->whereHasStatus('Pending:DM');
                  });
            $view = 'Smart/Manager/PendingDeactivationApprovals';
        }

        $approvals = $query->orderBy('id', 'desc')->get();

        return Inertia::render($view, [
            'user' => $request->user(),
            'approvals' => ManagerAssetStatusApprovalResource::collection($approvals)->resolve(),
        ]);
    }
}
