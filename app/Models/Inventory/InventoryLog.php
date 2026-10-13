<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventory Log model recording audit trail of stock, status, and assignment changes.
 */
class InventoryLog extends EloquentModel
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'model_id',
        'lot_id',
        'asset_id',
        'user_id',
        'action_type',
        'quantity_change',
        'previous_state',
        'new_state',
        'note',
        'document_url',
        'created_at',
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    /**
     * The catalog model associated with this log entry.
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(Model::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Model::class, 'model_id');
    }

    public function setBarangIdAttribute($value): void
    {
        $this->attributes['model_id'] = $value;
    }

    public function getBarangIdAttribute()
    {
        return $this->attributes['model_id'] ?? null;
    }

    /**
     * The inventory lot associated with this log entry.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * The specific asset associated with this log entry.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
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

    /**
     * The user who performed or triggered the logged action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
