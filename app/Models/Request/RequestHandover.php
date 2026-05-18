<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestHandover extends Model
{
    use HasFactory;

    protected $table = 'request_handovers';
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'user_confirmed_at' => 'datetime',
        'admin_cancelled_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
