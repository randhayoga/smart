<?php

namespace App\Models\Request;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestFulfillment extends Model
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
        'placement',
    ];

    protected $casts = [
        'quantity_fulfilled' => 'integer',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Request item associated with this fulfillment.
     */
    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class, 'request_item_id');
    }

    /**
     * Unit associated with this fulfillment (for serialized assets).
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Lot batch associated with this fulfillment (for consumable items).
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }

    /**
     * Handover schedule details linked to this fulfillment.
     */
    public function handover(): BelongsTo
    {
        return $this->belongsTo(RequestHandover::class, 'handover_id');
    }

    /**
     * Return schedule details linked to this fulfillment.
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(RequestReturn::class, 'return_id');
    }
}
