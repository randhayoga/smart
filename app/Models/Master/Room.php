<?php

namespace App\Models\Master;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Room model representing specific rooms or storage spaces on a floor.
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'floor_id',
    ];

    protected $casts = [
        'floor_id' => 'integer',
    ];

    /**
     * Floor on which this room is located.
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    /**
     * Lots with this as default room.
     * ROOM ||--o{ LOT : "default room"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Units in this room.
     * ROOM ||--o{ UNIT : "default room"
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
