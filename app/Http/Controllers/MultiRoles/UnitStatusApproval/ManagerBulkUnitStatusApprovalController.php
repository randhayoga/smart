<?php

namespace App\Http\Controllers\MultiRoles\UnitStatusApproval;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory\UnitStatusApproval;
use App\Actions\Inventory\ProcessUnitStatusApproval;

class ManagerBulkUnitStatusApprovalController extends Controller
{
    /**
     * Store a newly created bulk decision on asset status approvals.
     */
    public function store(Request $request, ProcessUnitStatusApproval $processApproval)
    {
        if (!in_array($request->user()->role, ['manager', 'ifs_manager'])) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:unit_status_approvals,id',
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'nullable|string',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['decision'];
        $note = $validated['note'];

        foreach ($ids as $id) {
            $approval = UnitStatusApproval::find($id);

            // Skip if not found or already processed
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
            ? 'Status perubahan aset berhasil disetujui.' 
            : 'Status perubahan aset berhasil ditolak.';

        return redirect()->back()->with('success', $message);
    }
}
