<?php

namespace App\Http\Controllers\Smart\MultiRoles\UnitStatusApproval;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use Illuminate\Http\Request;

class AdminUnitStatusApprovalController extends Controller
{
    /**
     * Store a newly created unit status approval request in storage.
     */
    public function store(Request $request)
    {
        $proposedCondition = $request->input('proposed_condition') ?? $request->input('proposed_status');

        $rules = [
            'unit_id' => 'required|exists:units,id',
            'proposed_condition' => 'required_without:proposed_status|string|max:255',
            'proposed_status' => 'required_without:proposed_condition|string|max:255',
            'note' => 'nullable|string',
            'memo_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];

        if ($proposedCondition === 'Hilang') {
            $rules['lost_doc_file'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
        }

        $validated = $request->validate($rules);

        $unit = Unit::findOrFail($validated['unit_id']);

        // Check if there is already a pending approval request for this unit
        $existingPending = UnitStatusApproval::where('unit_id', $unit->id)
            ->where('decision', 'pending')
            ->exists();

        if ($existingPending) {
            return redirect()->back()->withErrors([
                'unit_id' => 'Sudah ada pengajuan perubahan kondisi yang sedang ditinjau untuk unit ini.'
            ]);
        }

        $memoUrl = 'memos/placeholder.pdf';
        if ($request->hasFile('memo_file')) {
            $memoUrl = $request->file('memo_file')->store('memos', 'local');
        }

        $lostDocUrl = null;
        if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
            $lostDocUrl = $request->file('lost_doc_file')->store('lost_docs', 'local');
        }

        UnitStatusApproval::create([
            'unit_id' => $validated['unit_id'],
            'requester_id' => $request->user()->id,
            'proposed_condition' => $proposedCondition,
            'previous_condition' => $unit->condition,
            'previous_status' => str_starts_with($unit->status ?? '', 'Pending') ? 'Tersedia' : $unit->status,
            'decision' => 'pending',
            'note' => $validated['note'] ?? null,
            'requested_at' => now(),
            'memo_url' => $memoUrl,
            'lost_doc_url' => $lostDocUrl,
        ]);

        $unit->update(['status' => 'Pending:BoD/BoC']);

        return redirect()->back()->with('success', 'Pengajuan perubahan status unit berhasil dikirim.');
    }
}
