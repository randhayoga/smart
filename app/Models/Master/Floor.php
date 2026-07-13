<?php

namespace App\Models\Master;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location_id',
    ];

    protected $casts = [
        'location_id' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Lots with this as default floor.
     * FLOOR ||--o{ LOT : "default floor"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Units on this floor.
     * FLOOR ||--o{ UNIT : "default floor"
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
