<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetActivationApproval;

use App\Actions\Inventory\ProcessAssetActivationApproval;
use App\Http\Controllers\Controller;
use App\Models\Inventory\AssetActivationApproval;
use Illuminate\Http\Request;

/**
 * Manager Bulk Asset Activation Approval Controller processing batch decisions on asset activation requests.
 */
class ManagerBulkAssetActivationApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset activation approvals.
     */
    public function store(Request $request, ProcessAssetActivationApproval $processApproval)
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:asset_activation_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $note = $validated['note'];

        foreach ($ids as $id) {
            $approval = AssetActivationApproval::with('asset')->find($id);

            if (!$approval || $approval->decision !== 'pending') {
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
            ? __('inventory.activation_approved')
            : __('inventory.activation_rejected');

        return redirect()->back()->with('success', $message);
    }
}
