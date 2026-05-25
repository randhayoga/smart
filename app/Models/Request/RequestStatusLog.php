<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class RequestStatusLog extends Model
{
    protected $table = 'request_status_logs';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'status_from',
        'status_to',
        'changed_by',
        'note',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
