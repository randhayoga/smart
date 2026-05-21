<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'barang_id',
        'lot_id',
        'unit_id',
        'user_id',
        'action_type',
        'quantity_change',
        'previous_state',
        'new_state',
        'note'
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
