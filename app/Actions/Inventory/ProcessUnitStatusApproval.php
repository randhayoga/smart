<?php

namespace App\Actions\Inventory;

use App\Models\Inventory\Unit;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Inventory\UnitLifecycle;
use Illuminate\Support\Facades\DB;

class ProcessUnitStatusApproval
{
    /**
     * Execute the status approval process.
     */
    public function execute(UnitStatusApproval $approval, string $decision, ?string $note, int $approverId): void
    {
        DB::transaction(function () use ($approval, $decision, $note, $approverId) {
            $defaultNote = $decision === 'approved' ? 'Disetujui' : 'Ditolak';
            
            $approval->update([
                'decision' => $decision,
                'note' => $note ?? $approval->note ?? $defaultNote,
                'approver_id' => $approverId,
                'decided_at' => now(),
            ]);

            $unit = $approval->unit;
            if ($decision === 'approved') {
                $oldStatus = $approval->previous_status;
                $newStatus = 'Dihapus';

                // Update unit status
                $unit->update(['status' => $newStatus]);

                // Close any active unit lifecycles (end_date = now())
                UnitLifecycle::where('unit_id', $unit->id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                // Create new lifecycle log
                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'status' => $newStatus,
                    'start_date' => now(),
                    'end_date' => null,
                    'requester_id' => $approval->requester_id,
                    'approver_id' => $approverId,
                    'note' => $note ?? "Pembaruan status dari {$oldStatus} menjadi {$newStatus} (pengajuan: {$approval->proposed_status}) disetujui.",
                ]);
            } else {
                if ($unit) {
                    $unit->update(['status' => $approval->previous_status]);
                }
            }
        });
    }
}
