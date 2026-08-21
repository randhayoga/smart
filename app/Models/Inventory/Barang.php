<?php

namespace App\Models\Inventory;

use App\Models\Master\Brand;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'min_stock_threshold',
        'image_url',
        'last_restock_at'
    ];

    protected $casts = [
        'last_restock_at' => 'datetime',
        'subcategory_id' => 'integer',
        'brand_id' => 'integer',
        'uom_id' => 'integer',
        'min_stock_threshold' => 'integer',
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

    public function getNumberAttribute(?string $value): ?string
    {
        return $value !== null ? trim($value) : null;
    }

    /**
     * Get the route key for implicit route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * Retrieve the model for a bound value, supporting both code (number) and numeric ID.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return $this->where($field, $value)
            ->when(is_numeric($value), function ($query) use ($value) {
                $query->orWhere('id', $value);
            })
            ->first();
    }
}
