<?php

namespace App\Models\Request;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    use HasFactory;

    protected $table = 'request_approvals';
    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
