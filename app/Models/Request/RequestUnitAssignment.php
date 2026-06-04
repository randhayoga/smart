<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Inventory\Unit;

class RequestUnitAssignment extends Model
{
    protected $table = 'request_fulfillments';

    protected $fillable = [
        'request_item_id',
        'unit_id',
        'lot_id',
        'handover_id',
        'return_id',
        'quantity_fulfilled',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
