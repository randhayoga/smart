<?php

namespace App\Models\Master;

use App\Models\Inventory\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Barangs made by this brand.
     * BRAND ||--o{ BARANG : "made by"
     */
    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class);
    }
}
