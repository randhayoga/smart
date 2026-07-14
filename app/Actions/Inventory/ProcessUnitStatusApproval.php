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
                $unit->update(['status' => 'Dihapus']);
            } else {
                if ($unit) {
                    $unit->update(['status' => $approval->previous_status]);
                }
            }
        });
    }
}
