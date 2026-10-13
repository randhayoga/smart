<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetStatusApproval;
use App\Models\Inventory\AssetLifecycle;
use Illuminate\Support\Facades\DB;

/**
 * Process Asset Status Approval Action handling manager decisions on asset deactivation/disposal.
 */
class ProcessAssetStatusApproval
{
    /**
     * Execute the status approval process, updating asset state, lifecycle, and sending notifications.
     *
     * @param  \App\Models\Inventory\AssetStatusApproval  $approval
     * @param  string  $decision  'approved' or 'rejected'
     * @param  string|null  $note
     * @param  int  $approverId
     * @return void
     */
    public function execute(AssetStatusApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $approval->update([
                'decision' => $decision,
                'note' => $note,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $asset = $approval->asset;

            if ($decision === 'approved') {
                if ($asset) {
                    $asset->update([
                        'condition' => $approval->proposed_condition,
                        'status' => 'Tidak Aktif',
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

            app(\App\Services\NotificationService::class)->notifyAdminAssetStatusDecision($approval, $decision);
        });
    }
}
