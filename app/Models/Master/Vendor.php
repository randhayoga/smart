<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Lots supplied by this vendor.
     * VENDOR ||--o{ LOT : "supplied by"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Lot::class);
    }
}
