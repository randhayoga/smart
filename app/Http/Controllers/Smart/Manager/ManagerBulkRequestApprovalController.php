<?php

namespace App\Http\Controllers\Smart\Manager;

use App\Actions\Request\ProcessRequestApproval;
use App\Http\Controllers\Controller;
use App\Models\Request\Request as SmartRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manager Bulk Request Approval Controller processing batch decisions on requests.
 */
class ManagerBulkRequestApprovalController extends Controller
{
    /**
     * Memproses persetujuan (approve) atau penolakan (reject) secara massal oleh manager.
     */
    public function store(Request $request, ProcessRequestApproval $processApproval): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:requests,id',
            'action' => 'required|string|in:approve,reject',
            'note' => 'nullable|string|max:1000',
        ]);

        $ids = $validated['ids'];
        $decision = $validated['action'];
        $note = $validated['note'] ?? null;

        $requests = SmartRequest::where('approver_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->where('status', 'wait')
            ->get();

        foreach ($requests as $req) {
            $processApproval->execute(
                $req,
                $decision,
                $note,
                $request->user(),
                'in_app'
            );
        }

        $message = $decision === 'approve' 
            ? 'Beberapa permintaan berhasil disetujui.' 
            : 'Beberapa permintaan berhasil ditolak.';

        return redirect()->back()->with('success', $message);
    }
}
