<?php

namespace App\Models\Master;

use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Location model representing physical sites, branches, or buildings.
 */
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Floors contained within this location.
     */
    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    /**
     * Lots with this as default location.
     * LOCATION ||--o{ LOT : "default location"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Units currently at this location.
     * LOCATION ||--o{ UNIT : "currently at"
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
