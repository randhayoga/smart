<?php

namespace App\Http\Controllers\Smart\MultiRoles\AssetDeactivationApproval;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetStatusApproval;
use Illuminate\Http\Request;

/**
 * Admin Asset Deactivation Approval Controller handling submissions for asset condition changes, repairs, and disposals.
 */
class AdminAssetDeactivationApprovalController extends Controller
{
    /**
     * Store a newly created asset deactivation approval request in storage.
     */
    public function store(Request $request)
    {
        $proposedCondition = $request->input('proposed_condition');

        $rules = [
            'asset_id' => 'required|exists:assets,id',
            'proposed_condition' => 'required|string|max:255',
            'note' => 'nullable|string',
            'memo_file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:2048',
        ];

        if ($proposedCondition === 'Hilang') {
            $rules['lost_doc_file'] = 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:2048';
        }

        $validated = $request->validate($rules);

        $asset = Asset::findOrFail($validated['asset_id']);

        // Check if there is already a pending approval request for this asset
        $existingPending = AssetStatusApproval::where('asset_id', $asset->id)
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

        AssetStatusApproval::create([
            'asset_id' => $validated['asset_id'],
            'requester_id' => $request->user()->id,
            'proposed_condition' => $proposedCondition,
            'previous_condition' => $previousCondition,
            'previous_status' => $previousStatus,
            'decision' => 'pending',
            'note' => $validated['note'] ?? null,
            'requested_at' => now(),
            'memo_url' => $memoUrl,
            'lost_doc_url' => $lostDocUrl,
        ]);

        dispatch(function () use ($asset) {
            app(\App\Services\NotificationService::class)->notifyIfsManagerPendingDm($asset);
        })->afterResponse();

        return redirect()->back()->with('success', __('requests.unit_status_change_submitted'));
    }
}
