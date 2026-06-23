<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AdmUser;

class UnitStatusApproval extends Model
{
    use HasFactory;

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
        'doc_url',
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
