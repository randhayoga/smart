<?php

namespace App\Models\Request;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestApproval extends Model
{
    protected $table = 'request_approvals';

    protected $fillable = [
        'request_id',
        'approver_id',
        'decision',
        'note',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'approver_id');
    }
}
