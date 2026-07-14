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

            // Close any active unit lifecycles (end_date = now())
            UnitLifecycle::where('unit_id', $unit->id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            if ($decision === 'approved') {
                $oldStatus = $approval->previous_status;
                $newStatus = 'Dihapus';

                // Update unit status
                $unit->update(['status' => $newStatus]);

                // Create new lifecycle log for manager approval
                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'status' => $newStatus,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => $approverId,
                    'note' => $note ?? "Pembaruan status dari {$oldStatus} menjadi {$newStatus} (pengajuan: {$approval->proposed_status}) disetujui.",
                ]);
            } else {
                if ($unit) {
                    $unit->update(['status' => $approval->previous_status]);
                }

                // Create new lifecycle log for manager rejection
                UnitLifecycle::create([
                    'unit_id' => $unit->id,
                    'status' => $approval->previous_status,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => $approverId,
                    'note' => $note ?? "Pengajuan perubahan status dari {$approval->previous_status} menjadi {$approval->proposed_status} ditolak.",
                ]);
            }
        });
    }
}
