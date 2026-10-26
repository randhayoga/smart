<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetDeletionApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Process Asset Deletion Approval Action handling manager decisions on asset deletion/disposal.
 */
class ProcessAssetDeletionApproval
{
    /**
     * Execute the deletion approval process, updating asset state, lifecycle, and sending notifications.
     *
     * @param  \App\Models\Inventory\AssetDeletionApproval  $approval
     * @param  string  $decision  'approved' or 'rejected'
     * @param  string|null  $note
     * @param  int  $approverId
     * @return void
     */
    public function execute(AssetDeletionApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $approval->update([
                'decision' => $decision,
                'dm_note' => $note,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $asset = $approval->asset;

            if ($decision === 'approved') {
                if ($asset) {
                    // Delete and clear any previous BoD/BoC disapproval files immediately on DM approval
                    $previousDisapprovals = AssetDeletionApproval::where('asset_id', $asset->id)
                        ->whereNotNull('bod_boc_disapproval_url')
                        ->get();

                    foreach ($previousDisapprovals as $prev) {
                        if ($prev->bod_boc_disapproval_url && Storage::disk('local')->exists($prev->bod_boc_disapproval_url)) {
                            Storage::disk('local')->delete($prev->bod_boc_disapproval_url);
                        }
                        $prev->update(['bod_boc_disapproval_url' => null]);
                    }

                    $asset->update([
                        'status' => 'Tidak Aktif,Pending:BoD/BoC',
                    ]);
                }
            } else {
                if ($asset) {
                    $asset->update([
                        'status' => $approval->previous_status ?? 'Tersedia',
                        'condition' => $approval->previous_condition ?? $asset->condition,
                    ]);
                }
            }

            app(\App\Services\NotificationService::class)->notifyAdminAssetDeletionDecision($approval, $decision);
        });
    }
}
