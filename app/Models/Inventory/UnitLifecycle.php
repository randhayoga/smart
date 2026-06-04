<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AdmUser;

class UnitLifecycle extends Model
{
    use HasFactory;

    protected $table = 'unit_lifecycles';

    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'status',
        'start_date',
        'end_date',
        'requester_id',
        'approver_id',
        'note',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'approver_id');
    }
}
