<?php

namespace App\Http\Controllers\Smart\MultiRoles\UnitStatusApproval;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Http\Resources\AdminUnitStatusApprovalResource;
use App\Actions\Inventory\ProcessUnitStatusApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUnitStatusApprovalController extends Controller
{
    /**
     * Display a listing of the unit status approval requests.
     */
    public function index(Request $request)
    {
        $approvals = UnitStatusApproval::with([
            'unit.lot.barang.subcategory.category',
            'requester',
            'approver'
        ])
        ->latest()
        ->get();

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json(AdminUnitStatusApprovalResource::collection($approvals));
        }

        return Inertia::render('Smart/Admin/ManajemenStok/UnitStatusApprovalList', [
            'user' => $request->user(),
            'approvals' => AdminUnitStatusApprovalResource::collection($approvals)->resolve(),
        ]);
    }

    /**
     * Store a newly created unit status approval request in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'proposed_status' => 'required|string|max:255',
            'note' => 'nullable|string',
            'memo_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $unit = Unit::findOrFail($validated['unit_id']);

        // Check if there is already a pending approval request for this unit
        $existingPending = UnitStatusApproval::where('unit_id', $unit->id)
            ->where('decision', 'pending')
            ->exists();

        if ($existingPending) {
            return redirect()->back()->withErrors([
                'unit_id' => 'Sudah ada pengajuan perubahan status yang sedang ditinjau untuk unit ini.'
            ]);
        }

        $docUrl = 'memos/placeholder.pdf';
        if ($request->hasFile('memo_file')) {
            $docUrl = $request->file('memo_file')->store('memos', 'public');
        }

        UnitStatusApproval::create([
            'unit_id' => $validated['unit_id'],
            'requester_id' => $request->user()->id,
            'proposed_status' => $validated['proposed_status'],
            'previous_status' => $unit->status,
            'decision' => 'pending',
            'note' => $validated['note'] ?? null,
            'requested_at' => now(),
            'doc_url' => $docUrl,
        ]);

        $unit->update(['status' => 'Pending']);

        return redirect()->back()->with('success', 'Pengajuan perubahan status unit berhasil dikirim.');
    }

    /**
     * Display the specified unit status approval request.
     */
    public function show(Request $request, UnitStatusApproval $unitStatusApproval)
    {
        $unitStatusApproval->load([
            'unit.lot.barang.subcategory.category',
            'requester',
            'approver'
        ]);

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json(new AdminUnitStatusApprovalResource($unitStatusApproval));
        }

        return Inertia::render('Smart/Admin/ManajemenStok/UnitStatusApprovalDetail', [
            'user' => $request->user(),
            'approval' => (new AdminUnitStatusApprovalResource($unitStatusApproval))->resolve(),
        ]);
    }

    /**
     * Update the specified unit status approval request (approve/reject).
     */
    public function update(Request $request, UnitStatusApproval $unitStatusApproval, ProcessUnitStatusApproval $processApproval)
    {
        if ($unitStatusApproval->decision !== 'pending') {
            return redirect()->back()->withErrors([
                'decision' => 'Pengajuan perubahan status ini sudah diproses.'
            ]);
        }

        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'note' => 'required_if:decision,rejected|nullable|string',
        ]);

        $processApproval->execute(
            $unitStatusApproval,
            $validated['decision'],
            $validated['note'] ?? null,
            $request->user()->id
        );

        $message = $validated['decision'] === 'approved'
            ? 'Pengajuan perubahan status unit disetujui.'
            : 'Pengajuan perubahan status unit ditolak.';

        return redirect()->back()->with('success', $message);
    }

    /**
     * Remove the specified unit status approval request from storage.
     */
    public function destroy(UnitStatusApproval $unitStatusApproval)
    {
        if ($unitStatusApproval->decision !== 'pending') {
            return redirect()->back()->withErrors([
                'error' => 'Hanya pengajuan yang masih pending yang dapat dihapus/dibatalkan.'
            ]);
        }

        $unit = $unitStatusApproval->unit;
        $previousStatus = $unitStatusApproval->previous_status;

        $unitStatusApproval->delete();

        if ($unit) {
            $unit->update(['status' => $previousStatus]);
        }

        return redirect()->back()->with('success', 'Pengajuan perubahan status unit berhasil dibatalkan.');
    }
}
