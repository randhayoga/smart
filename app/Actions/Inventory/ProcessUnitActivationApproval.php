<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\UnitActivationApproval;
use App\Models\Inventory\UnitLifecycle;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Process Unit Activation Approval Action handling manager decisions on new unit registration.
 */
class ProcessUnitActivationApproval
{
    /**
     * Execute the activation approval process, updating unit state, lifecycle, and sending notifications.
     *
     * @param  \App\Models\Inventory\UnitActivationApproval  $approval
     * @param  string  $decision  'approved' or 'rejected'
     * @param  string|null  $note
     * @param  int  $approverId
     * @return void
     */
    public function execute(UnitActivationApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $approval->update([
                'decision' => $decision,
                'note' => $note,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $unit = $approval->unit;
            if (!$unit) {
                return;
            }

            // Close existing open lifecycles
            UnitLifecycle::where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            if ($decision === 'approved') {
                $unit->update([
                    'status' => 'Tersedia',
                ]);
                $lifecycleNote = 'Aktivasi aset disetujui oleh DM IFS.' . ($note ? " Catatan: {$note}" : '');
            } else {
                $unit->update([
                    'status' => 'Verifikasi Ditolak',
                ]);
                $lifecycleNote = 'Aktivasi aset ditolak oleh DM IFS.' . ($note ? " Catatan: {$note}" : '');
            }

            UnitLifecycle::create([
                'unit_id' => $unit->id,
                'action_type' => 'Approval',
                'status' => $unit->status,
                'condition' => $unit->condition,
                'location_id' => $unit->location_id,
                'start_date' => now(),
                'end_date' => null,
                'actor_id' => $approverId,
                'note' => $lifecycleNote,
            ]);

            app(NotificationService::class)->notifyAdminAssetActivationDecision($approval, $decision);
        });
    }
}
