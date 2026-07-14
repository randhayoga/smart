<?php

namespace App\Models\Inventory;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Inventory\UnitLifecycle;

class UnitStatusApproval extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::created(function ($approval) {
            // Close any active unit lifecycles
            UnitLifecycle::where('unit_id', $approval->unit_id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            // Create requester lifecycle entry
            UnitLifecycle::create([
                'unit_id' => $approval->unit_id,
                'status' => $approval->proposed_status,
                'start_date' => now(),
                'end_date' => null,
                'actor_id' => $approval->requester_id,
                'note' => $approval->note ?? 'Pengajuan perubahan status unit',
            ]);
        });

        static::deleted(function ($approval) {
            if ($approval->decision === 'pending') {
                // Close any active unit lifecycles
                UnitLifecycle::where('unit_id', $approval->unit_id)
                    ->whereNull('end_date')
                    ->update(['end_date' => now()]);

                // Create a new active lifecycle entry for the reverted status
                UnitLifecycle::create([
                    'unit_id' => $approval->unit_id,
                    'status' => $approval->previous_status,
                    'start_date' => now(),
                    'end_date' => null,
                    'actor_id' => auth()->id() ?? $approval->requester_id,
                    'note' => 'Pembatalan pengajuan perubahan status unit',
                ]);
            }
        });
    }

    protected $fillable = [
        'unit_id',
        'requester_id',
        'approver_id',
        'proposed_status',
        'previous_status',
        'decision',
        'note',
        'requested_at',
        'decided_at',
        'memo_url',
        'lost_doc_url',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'approver_id');
    }
}
