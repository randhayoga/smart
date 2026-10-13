<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetActivationApproval;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerAssetActivationApprovalResource;
use App\Models\Inventory\AssetActivationApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Asset Activation Approval Controller displaying pending and historical asset activation reviews.
 */
class ManagerAssetActivationApprovalController extends Controller
{
    /**
     * Display a listing of asset activation approvals for managers (pending or history).
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $query = AssetActivationApproval::with([
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
            $view = 'Smart/Manager/SudahApproveAktivasi';
        } else {
            $query->where('decision', 'pending');
            $view = 'Smart/Manager/PerluApproveAktivasi';
        }

        $approvals = $query->orderBy('id', 'desc')->get();

        return Inertia::render($view, [
            'user' => $request->user(),
            'approvals' => ManagerAssetActivationApprovalResource::collection($approvals)->resolve(),
        ]);
    }
}
