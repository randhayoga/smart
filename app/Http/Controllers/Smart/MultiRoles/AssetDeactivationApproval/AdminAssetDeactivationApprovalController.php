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
        $proposedCondition = $request->input('proposed_condition') ?? $request->input('proposed_status');

        $assetId = $request->input('asset_id', $request->input('unit_id'));
        $request->merge(['asset_id' => $assetId]);

        $rules = [
            'asset_id' => 'required|exists:assets,id',
            'proposed_condition' => 'required_without:proposed_status|string|max:255',
            'proposed_status' => 'required_without:proposed_condition|string|max:255',
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
                'unit_id' => 'Sudah ada pengajuan perubahan kondisi yang sedang ditinjau untuk aset ini.',
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

        AssetStatusApproval::create([
            'asset_id' => $validated['asset_id'],
            'requester_id' => $request->user()->id,
            'proposed_condition' => $proposedCondition,
            'previous_condition' => $asset->condition,
            'previous_status' => str_starts_with($asset->status ?? '', 'Pending') ? 'Tersedia' : $asset->status,
            'decision' => 'pending',
            'note' => $validated['note'] ?? null,
            'requested_at' => now(),
            'memo_url' => $memoUrl,
            'lost_doc_url' => $lostDocUrl,
        ]);

        $asset->update(['status' => 'Pending:BoD/BoC']);

        return redirect()->back()->with('success', __('requests.unit_status_change_submitted'));
    }
}
