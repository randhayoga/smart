<?php

namespace App\Models\Master;

use App\Models\Inventory\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master UOM (Unit of Measure) model representing units of measurement (e.g. PCS, UNIT, BOX).
 */
class Uom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Barangs measured by this UOM.
     * UOM ||--o{ BARANG : "measures"
     */
    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class);
    }
}
