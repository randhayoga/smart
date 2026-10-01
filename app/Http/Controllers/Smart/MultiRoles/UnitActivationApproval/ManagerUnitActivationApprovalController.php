<?php

namespace App\Http\Controllers\Smart\MultiRoles\UnitActivationApproval;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerAssetActivationApprovalResource;
use App\Models\Inventory\UnitActivationApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manager Unit Activation Approval Controller displaying pending and historical asset unit activation reviews.
 */
class ManagerUnitActivationApprovalController extends Controller
{
    /**
     * Display a listing of unit activation approvals for managers (pending or history).
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $query = UnitActivationApproval::with([
            'unit.lot.barang.subcategory.category',
            'unit.lot.barang.brand',
            'unit.lot.barang.uom',
            'unit.lot.organizer',
            'unit.lot.vendor',
            'unit.lot.legacyVendor',
            'unit.location.parent',
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
