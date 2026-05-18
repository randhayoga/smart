<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestReturn extends Model
{
    use HasFactory;

    protected $table = 'request_returns';
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
