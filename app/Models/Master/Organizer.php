<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Lots managed by this organizer.
     * ORGANIZER ||--o{ LOT : "managed by"
     */
    public function lots(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\Lot::class);
    }
}
