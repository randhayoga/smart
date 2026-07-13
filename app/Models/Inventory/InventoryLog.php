<?php

namespace App\Models\Inventory;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'barang_id',
        'lot_id',
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

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'user_id');
    }
}
