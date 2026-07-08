<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;

class Barang extends Model
{
    use HasFactory;

    protected $with = ['subcategory', 'brand', 'uom'];

    protected $fillable = [
        'number',
        'subcategory_id',
        'brand_id',
        'uom_id',
        'name',
        'specification',
        'image_url',
        'last_restock_at'
    ];

    protected $casts = [
        'last_restock_at' => 'datetime',
        'subcategory_id' => 'integer',
        'brand_id' => 'integer',
        'uom_id' => 'integer',
    ];

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Inventory logs for this barang.
     * BARANG ||--o{ INVENTORY_LOG : "logged"
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function getNumberAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
