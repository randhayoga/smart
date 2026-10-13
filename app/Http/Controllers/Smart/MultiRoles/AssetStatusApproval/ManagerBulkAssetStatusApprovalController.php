<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetStatusApproval;

use App\Actions\Inventory\ProcessAssetStatusApproval;
use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetStatusApproval;
use Illuminate\Http\Request;

/**
 * Manager Bulk Asset Status Approval Controller processing batch decisions on asset condition change requests.
 */
class ManagerBulkAssetStatusApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset status approvals.
     */
    public function store(Request $request, ProcessAssetStatusApproval $processApproval)
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_status_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $note = $validated['note'];

        foreach ($ids as $id) {
            $approval = AssetStatusApproval::with('asset')->find($id);

            // Skip if not found, decision is not pending, or asset status is Pending:BoD/BoC
            if (!$approval || $approval->decision !== 'pending' || $approval->asset?->status === 'Pending:BoD/BoC') {
                continue;
            }

            $processApproval->execute(
                $approval,
                $decision,
                $note,
                $request->user()->id
            );
        }

        $message = $decision === 'approved' 
            ? __('requests.unit_status_approved') 
            : __('requests.unit_status_rejected');

        return redirect()->back()->with('success', $message);
    }
}
