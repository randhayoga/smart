<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetLifecycle;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Process Asset Activation Approval Action handling manager decisions on new asset registration.
 */
class ProcessAssetActivationApproval
{
    /**
     * Execute the activation approval process, updating asset state, lifecycle, and sending notifications.
     *
     * @param  \App\Models\Inventory\AssetActivationApproval  $approval
     * @param  string  $decision  'approved' or 'rejected'
     * @param  string|null  $note
     * @param  int  $approverId
     * @return void
     */
    public function execute(AssetActivationApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $approval->update([
                'decision' => $decision,
                'note' => $note,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $asset = $approval->asset;
            if (!$asset) {
                return;
            }

            // Close existing open lifecycles
            AssetLifecycle::where('asset_id', $asset->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            $previousStatus = $asset->status;

            // Suppress duplicate "Perubahan status" lifecycle created by Asset::updating hook
            Asset::withoutLifecycleLogging(function () use ($asset, $decision) {
                if ($decision === 'approved') {
                    $asset->update([
                        'status' => 'Tersedia',
                    ]);
                } else {
                    $asset->update([
                        'status' => 'Verifikasi Ditolak',
                    ]);
                }
            });

            if ($decision === 'approved') {
                $lifecycleNote = 'Aktivasi aset disetujui oleh DM IFS.' . ($note ? " Catatan: {$note}" : '');
            } else {
                $lifecycleNote = 'Aktivasi aset ditolak oleh DM IFS.' . ($note ? " Catatan: {$note}" : '');
            }

            AssetLifecycle::create([
                'asset_id' => $asset->id,
                'action_type' => 'Approval',
                'status' => $asset->status,
                'condition' => $asset->condition,
                'location_id' => $asset->location_id,
                'start_date' => now(),
                'end_date' => null,
                'actor_id' => $approverId,
                'note' => $lifecycleNote,
                'previous_state' => ['status' => $previousStatus],
                'new_state' => ['status' => $asset->status],
            ]);

            app(NotificationService::class)->notifyAdminAssetActivationDecision($approval, $decision);
        });
    }
}
