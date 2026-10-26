<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Inventory\AssetLifecycle;

/**
 * Asset Deletion Approval model representing workflow approval requests for asset deletion / disposal.
 */
class AssetDeletionApproval extends Model
{
    use HasFactory;

    protected $table = 'asset_deletion_approvals';

    protected static function booted()
    {
        static::created(function ($approval) {
            $asset = $approval->asset;

            // Close any active asset lifecycles
            AssetLifecycle::where('asset_id', $approval->asset_id)
                ->whereNull('end_date')
                ->update(['end_date' => now()]);

            // Create requester lifecycle entry
            AssetLifecycle::create([
                'asset_id' => $approval->asset_id,
                'action_type' => 'Perubahan kondisi',
                'status' => $asset->status ?? 'Pending',
                'condition' => $approval->proposed_condition,
                'location_id' => $asset->location_id,
                'start_date' => now(),
                'end_date' => null,
                'actor_id' => $approval->requester_id,
                'note' => $approval->admin_note ?? "Pengajuan perubahan kondisi aset dari '{$approval->previous_condition}' menjadi '{$approval->proposed_condition}'",
            ]);
        });
    }

    protected $fillable = [
        'asset_id',
        'requester_id',
        'approver_id',
        'proposed_condition',
        'previous_condition',
        'previous_status',
        'decision',
        'admin_note',
        'dm_note',
        'requested_at',
        'decided_at',
        'memo_url',
        'lost_doc_url',
        'bod_boc_approval_url',
        'bod_boc_disapproval_url',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * The asset undergoing deletion approval.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * The user who requested the deletion.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The user who approved or rejected the deletion.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
