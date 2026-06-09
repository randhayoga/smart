<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UnitStatusApprovalController extends Controller
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
        ->get()
        ->map(function ($approval) {
            return [
                'id' => $approval->id,
                'unit_id' => $approval->unit_id,
                'unit_number' => $approval->unit->number ?? '-',
                'proposed_status' => $approval->proposed_status,
                'decision' => $approval->decision,
                'note' => $approval->note,
                'requester_name' => $approval->requester->name ?? '-',
                'approver_name' => $approval->approver->name ?? '-',
                'requested_at' => $approval->requested_at ? $approval->requested_at->format('d/m/Y H:i') : '-',
                'decided_at' => $approval->decided_at ? $approval->decided_at->format('d/m/Y H:i') : '-',
            ];
        });

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json($approvals);
        }

        return Inertia::render('Smart/Admin/ManajemenStok/UnitStatusApprovalList', [
            'user' => $request->user(),
            'approvals' => $approvals,
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

        UnitStatusApproval::create([
            'unit_id' => $validated['unit_id'],
            'requester_id' => $request->user()->id,
            'proposed_status' => $validated['proposed_status'],
            'decision' => 'pending',
            'note' => $validated['note'] ?? null,
            'requested_at' => now(),
        ]);

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

        $formatted = [
            'id' => $unitStatusApproval->id,
            'unit_id' => $unitStatusApproval->unit_id,
            'unit_number' => $unitStatusApproval->unit->number ?? '-',
            'proposed_status' => $unitStatusApproval->proposed_status,
            'decision' => $unitStatusApproval->decision,
            'note' => $unitStatusApproval->note,
            'requester_name' => $unitStatusApproval->requester->name ?? '-',
            'approver_name' => $unitStatusApproval->approver->name ?? '-',
            'requested_at' => $unitStatusApproval->requested_at ? $unitStatusApproval->requested_at->format('d/m/Y H:i') : '-',
            'decided_at' => $unitStatusApproval->decided_at ? $unitStatusApproval->decided_at->format('d/m/Y H:i') : '-',
        ];

        if ($request->wantsJson() && !$request->headers->has('X-Inertia')) {
            return response()->json($formatted);
        }

        return Inertia::render('Smart/Admin/ManajemenStok/UnitStatusApprovalDetail', [
            'user' => $request->user(),
            'approval' => $formatted,
        ]);
    }

    /**
     * Update the specified unit status approval request (approve/reject).
     */
    public function update(Request $request, UnitStatusApproval $unitStatusApproval)
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

        DB::transaction(function () use ($unitStatusApproval, $validated, $request) {
            $unitStatusApproval->update([
                'decision' => $validated['decision'],
                'note' => $validated['note'] ?? $unitStatusApproval->note,
                'approver_id' => $request->user()->id,
                'decided_at' => now(),
            ]);

            if ($validated['decision'] === 'approved') {
                $unit = $unitStatusApproval->unit;
                $unit->update([
                    'status' => $unitStatusApproval->proposed_status,
                ]);

                // End any active lifecycles
                UnitLifecycle::where('unit_id', $unit->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                // Start new lifecycle record
                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'status' => $unitStatusApproval->proposed_status,
                    'start_date' => now(),
                    'requester_id' => $unitStatusApproval->requester_id,
                    'approver_id' => $request->user()->id,
                    'note' => $validated['note'] ?? $unitStatusApproval->note,
                ]);
            }
        });

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

        $unitStatusApproval->delete();

        return redirect()->back()->with('success', 'Pengajuan perubahan status unit berhasil dibatalkan.');
    }
}
