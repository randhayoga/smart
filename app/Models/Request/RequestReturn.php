<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestReturn extends Model
{
    protected $table = 'request_returns';

    protected $fillable = [
        'request_id',
        'method',
        'scheduled_date',
        'location',
        'is_auto_set',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'completed_at' => 'datetime',
        'is_auto_set' => 'boolean',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
