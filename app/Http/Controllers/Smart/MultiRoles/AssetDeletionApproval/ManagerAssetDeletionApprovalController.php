<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeletionApproval;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerAssetDeletionApprovalResource;
use App\Models\Inventory\AssetDeletionApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Asset Deletion Approval Controller displaying pending and historical asset deletion reviews.
 */
class ManagerAssetDeletionApprovalController extends Controller
{
    /**
     * Display a listing of asset deletion approvals for managers (pending or history).
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $query = AssetDeletionApproval::with([
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
            $view = 'Smart/Manager/ProcessedDeletionApprovals';
        } else {
            $query->where('decision', 'pending')
                  ->whereHas('asset', function ($q) {
                      $q->whereHasStatus('Pending:DM');
                  });
            $view = 'Smart/Manager/PendingDeletionApprovals';
        }

        $approvals = $query->orderBy('id', 'desc')->get();

        return Inertia::render($view, [
            'user' => $request->user(),
            'approvals' => ManagerAssetDeletionApprovalResource::collection($approvals)->resolve(),
        ]);
    }
}
