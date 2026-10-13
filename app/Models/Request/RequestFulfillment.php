<?php

namespace App\Models\Request;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestFulfillment extends Model
{
    protected $table = 'request_fulfillments';

    protected $fillable = [
        'request_item_id',
        'asset_id',
        'unit_id',
        'lot_id',
        'handover_id',
        'return_id',
        'quantity_fulfilled',
        'assigned_at',
        'confirmed_at',
        'completed_at',
    ];

    protected $casts = [
        'quantity_fulfilled' => 'integer',
        'assigned_at' => 'datetime',
        'confirmed_at' => 'datetime',
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
     * Asset associated with this fulfillment (for serialized assets).
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function setUnitIdAttribute($value): void
    {
        $this->attributes['asset_id'] = $value;
    }

    public function getUnitIdAttribute()
    {
        return $this->attributes['asset_id'] ?? null;
    }

    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if (is_string($column) && ($column === 'unit_id' || $column === 'request_fulfillments.unit_id')) {
                    $column = str_replace('unit_id', 'asset_id', $column);
                }
                return parent::where($column, $operator, $value, $boolean);
            }
        };
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
