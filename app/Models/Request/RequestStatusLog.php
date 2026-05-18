<?php

namespace App\Models\Request;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestStatusLog extends Model
{
    use HasFactory;

    protected $table = 'request_status_logs';
    protected $guarded = [];
    public $timestamps = false; // per schema uses a single custom timestamp 'created_at'

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
