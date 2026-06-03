<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

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
        return $this->hasMany(\App\Models\Inventory\Lot::class);
    }

    /**
     * Units currently at this location.
     * LOCATION ||--o{ UNIT : "currently at"
     */
    public function units(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Unit::class);
    }
}
