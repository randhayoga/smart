<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\TbProject;

class Request extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'request_number',
        'user_id',
        'approver_id',
        'utilization',
        'org_id',
        'project_id',
        'reasoning',
        'status',
    ];

    protected $casts = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'approver_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'org_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TbProject::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class);
    }

    public function approval(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RequestApproval::class, 'request_id')->latestOfMany();
    }

    public function adminConfirmation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RequestAdminConfirmation::class, 'request_id')->latestOfMany();
    }

    public function handover(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RequestHandover::class, 'request_id');
    }

    public function return(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RequestReturn::class, 'request_id');
    }

    /**
     * Accessor for start_date from the first request item.
     */
    public function getStartDateAttribute()
    {
        return $this->items->first()?->start_date;
    }

    /**
     * Accessor for end_date from the first request item.
     */
    public function getEndDateAttribute()
    {
        return $this->items->first()?->end_date;
    }
}

