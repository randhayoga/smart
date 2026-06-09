<?php

namespace App\Http\Controllers\MultiRoles\UnitStatusApproval;

use App\Http\Controllers\Controller;
use App\Models\Inventory\UnitStatusApproval;
use App\Http\Resources\ManagerAssetStatusApprovalResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagerUnitStatusApprovalController extends Controller
{
    /**
     * Display a listing of unit status approvals for managers (pending or history).
     */
    public function index(Request $request): Response
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Manager.');
        }

        $query = UnitStatusApproval::with([
            'unit.lot.barang.subcategory.category',
            'unit.lot.barang.brand',
            'unit.lot.barang.uom',
            'unit.lot.organizer',
            'unit.lot.vendor',
            'unit.location',
            'unit.floor',
            'unit.room',
            'requester',
            'approver'
        ]);

        if ($request->boolean('history')) {
            $query->where('decision', '!=', 'pending');
            $view = 'Smart/Manager/SudahApproveStatus';
        } else {
            $query->where('decision', 'pending');
            $view = 'Smart/Manager/PerluApproveStatus';
        }

        $approvals = $query->orderBy('id', 'desc')->get();

        return Inertia::render($view, [
            'user' => $request->user(),
            'approvals' => ManagerAssetStatusApprovalResource::collection($approvals)->resolve(),
        ]);
    }
}
