<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asset Activation Approval model representing workflow approval requests for newly created assets.
 */
class AssetActivationApproval extends Model
{
    use HasFactory;

    protected $table = 'asset_activation_approvals';

    protected $fillable = [
        'asset_id',
        'unit_id',
        'requester_id',
        'approver_id',
        'decision',
        'note',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * The asset undergoing activation approval.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function setUnitIdAttribute($value): void
    {
        $this->attributes['asset_id'] = $value;
    }

    public function getUnitIdAttribute()
    {
        return $this->attributes['asset_id'] ?? null;
    }

    /**
     * The user who registered/created the asset and requested activation.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The manager who approved or rejected the activation request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
