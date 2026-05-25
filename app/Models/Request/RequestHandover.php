<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestHandover extends Model
{
    protected $table = 'request_handovers';

    protected $fillable = [
        'request_id',
        'method',
        'scheduled_date',
        'location',
        'is_auto_set',
        'user_confirmed_at',
        'auto_confirmed',
        'admin_cancelled_at',
        'note',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'user_confirmed_at' => 'datetime',
        'admin_cancelled_at' => 'datetime',
        'auto_confirmed' => 'boolean',
        'is_auto_set' => 'boolean',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
