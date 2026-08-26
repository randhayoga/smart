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
     * Get the route key value for generating URLs (alphanumeric only).
     */
    public function getRouteKey(): mixed
    {
        $number = $this->getAttribute($this->getRouteKeyName());
        if ($number !== null) {
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', (string)$number);
            if ($clean !== '') {
                return $clean;
            }
        }
        return $this->getKey();
    }

    /**
     * Retrieve the model for a bound value, supporting alphanumeric code, raw code (number), and numeric ID.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        // 1. Direct exact match
        $model = $this->where($field, $value)->first();
        if ($model) {
            return $model;
        }

        // 2. Numeric ID match
        if (is_numeric($value)) {
            $model = $this->where('id', $value)->first();
            if ($model) {
                return $model;
            }
        }

        // 3. Alphanumeric normalized match
        $cleanValue = preg_replace('/[^a-zA-Z0-9]/', '', (string)$value);
        if (!empty($cleanValue)) {
            $model = $this->whereRaw(
                "LOWER(REPLACE(REPLACE(REPLACE(REPLACE({$field}, '-', ''), ' ', ''), '_', ''), '/', '')) = ?",
                [strtolower($cleanValue)]
            )->first();

            if ($model) {
                return $model;
            }

            // Fallback in-memory check
            return $this->all()->first(function ($item) use ($field, $cleanValue) {
                $itemClean = preg_replace('/[^a-zA-Z0-9]/', '', (string)($item->{$field} ?? ''));
                return strcasecmp($itemClean, $cleanValue) === 0;
            });
        }

        return null;
    }
}
