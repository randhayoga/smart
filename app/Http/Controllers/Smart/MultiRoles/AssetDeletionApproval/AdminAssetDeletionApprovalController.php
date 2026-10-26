<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeletionApproval;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use Illuminate\Http\Request;

/**
 * Admin Asset Deletion Approval Controller handling submissions for asset condition changes and disposals.
 */
class AdminAssetDeletionApprovalController extends Controller
{
    /**
     * Store a newly created asset deletion approval request in storage.
     */
    public function store(Request $request)
    {
        $proposedCondition = $request->input('proposed_condition');

        $rules = [
            'asset_id' => 'required|exists:assets,id',
            'proposed_condition' => 'required|string|max:255',
            'admin_note' => 'nullable|string',
            'memo_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:2048',
        ];

        if ($proposedCondition === 'Hilang') {
            $rules['lost_doc_file'] = 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:2048';
        }

        $validated = $request->validate($rules);

        $asset = Asset::findOrFail($validated['asset_id']);

        // Check if there is already a pending approval request for this asset
        $existingPending = AssetDeletionApproval::where('asset_id', $asset->id)
            ->where('decision', 'pending')
            ->exists();

        if ($existingPending) {
            return redirect()->back()->withErrors([
                'asset_id' => 'Sudah ada pengajuan perubahan kondisi yang sedang ditinjau untuk aset ini.',
            ]);
        }

        $imageUploadService = app(\App\Services\ImageUploadService::class);

        $memoUrl = 'memos/placeholder.pdf';
        if ($request->hasFile('memo_file')) {
            $memoUrl = $imageUploadService->storeImageOrFile($request->file('memo_file'), 'memos');
        }

        $lostDocUrl = null;
        if ($proposedCondition === 'Hilang' && $request->hasFile('lost_doc_file')) {
            $lostDocUrl = $imageUploadService->storeImageOrFile($request->file('lost_doc_file'), 'lost_docs');
        }

        $previousCondition = $asset->condition;
        $previousStatus = $asset->base_status;

        $asset->update([
            'condition' => $proposedCondition,
            'status' => "{$previousStatus},Pending:DM",
        ]);

        $approval = AssetDeletionApproval::create([
            'asset_id' => $validated['asset_id'],
            'requester_id' => $request->user()->id,
            'proposed_condition' => $proposedCondition,
            'previous_condition' => $previousCondition,
            'previous_status' => $previousStatus,
            'decision' => 'pending',
            'admin_note' => $validated['admin_note'] ?? null,
            'requested_at' => now(),
            'memo_url' => $memoUrl,
            'lost_doc_url' => $lostDocUrl,
        ]);

        dispatch(function () use ($asset, $approval) {
            app(\App\Services\NotificationService::class)->notifyManagerAssetDeletionChange($asset, $approval);
        })->afterResponse();

        return redirect()->back()->with('success', __('requests.unit_status_change_submitted'));
    }
}
