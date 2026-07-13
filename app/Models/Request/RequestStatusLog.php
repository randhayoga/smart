<?php

namespace App\Models\Request;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusLog extends Model
{
    protected $table = 'request_status_logs';

    const UPDATED_AT = null;

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
        return $this->belongsTo(AdmUser::class, 'changed_by');
    }
}
