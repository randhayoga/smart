<?php

namespace App\Models\Inventory;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventory Log model recording audit trail of stock, status, and assignment changes.
 */
class InventoryLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'barang_id',
        'lot_id',
        'unit_id',
        'user_id',
        'action_type',
        'quantity_change',
        'previous_state',
        'new_state',
        'note',
        'document_url'
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    /**
     * The catalog item associated with this log entry.
     */
    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    /**
     * The inventory lot associated with this log entry.
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * The specific asset unit associated with this log entry.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The user who performed or triggered the logged action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'user_id');
    }
}
