<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeletionApproval;

use App\Actions\Inventory\ProcessAssetDeletionApproval;
use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetDeletionApproval;
use Illuminate\Http\Request;

/**
 * Manager Bulk Asset Deletion Approval Controller processing batch decisions on asset deletion requests.
 */
class ManagerBulkAssetDeletionApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset deletion approvals.
     */
    public function store(Request $request, ProcessAssetDeletionApproval $processApproval)
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_deletion_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'dm_note' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $dmNote = $validated['dm_note'] ?? $validated['note'] ?? null;

        $approvals = AssetDeletionApproval::with('asset.lot.model.brand')
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
                $dmNote,
                $request->user()->id
            );
        }

        $message = $decision === 'approved' 
            ? __('requests.unit_status_approved') 
            : __('requests.unit_status_rejected');

        return redirect()->back()->with('success', $message);
    }
}
