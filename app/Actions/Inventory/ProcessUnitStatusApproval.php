<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use Illuminate\Support\Facades\DB;

/**
 * Process Unit Status Approval Action handling manager decisions on asset deactivation/disposal.
 */
class ProcessUnitStatusApproval
{
    /**
     * Execute the status approval process, updating unit state, lifecycle, and sending notifications.
     *
     * @param  \App\Models\Inventory\UnitStatusApproval  $approval
     * @param  string  $decision  'approved' or 'rejected'
     * @param  string|null  $note
     * @param  int  $approverId
     * @return void
     */
    public function execute(UnitStatusApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $approval->update([
                'decision' => $decision,
                'note' => $note,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $unit = $approval->unit;

            if ($decision === 'approved') {
                if ($unit) {
                    $unit->update([
                        'condition' => $approval->proposed_condition,
                        'status' => 'Tidak Aktif',
                    ]);
                }
            } else {
                if ($unit) {
                    $unit->update([
                        'status' => $approval->previous_status ?? 'Tersedia',
                        'condition' => $approval->previous_condition ?? $unit->condition,
                    ]);
                }
            }

            app(\App\Services\NotificationService::class)->notifyAdminAssetStatusDecision($approval, $decision);
        });
    }
}
