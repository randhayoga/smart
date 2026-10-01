<?php

namespace App\Http\Controllers\Smart\MultiRoles\UnitActivationApproval;

use App\Actions\Inventory\ProcessUnitActivationApproval;
use App\Http\Controllers\Controller;
use App\Models\Inventory\UnitActivationApproval;
use Illuminate\Http\Request;

/**
 * Manager Bulk Unit Activation Approval Controller processing batch decisions on asset activation requests.
 */
class ManagerBulkUnitActivationApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset activation approvals.
     */
    public function store(Request $request, ProcessUnitActivationApproval $processApproval)
    {
        if (!$request->user()->hasPermission('inventory.status_approval.decide')) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:unit_activation_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $note = $validated['note'];

        foreach ($ids as $id) {
            $approval = UnitActivationApproval::with('unit')->find($id);

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
