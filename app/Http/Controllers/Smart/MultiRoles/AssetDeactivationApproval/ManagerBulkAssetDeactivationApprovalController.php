<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval;

use App\Actions\Inventory\ProcessAssetStatusApproval;
use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetStatusApproval;
use Illuminate\Http\Request;

/**
 * Manager Bulk Asset Deactivation Approval Controller processing batch decisions on asset deactivation requests.
 */
class ManagerBulkAssetDeactivationApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset deactivation approvals.
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

        $approvals = AssetStatusApproval::with('asset.lot.model.brand')
            ->whereIn('id', $ids)
            ->where('decision', 'pending')
            ->get();

        foreach ($approvals as $approval) {
            if (!$approval->asset || !$approval->asset->hasStatus('Pending:DM')) {
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
