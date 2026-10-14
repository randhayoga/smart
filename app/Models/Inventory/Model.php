<?php

namespace App\Models\Inventory;

use App\Models\Master\Brand;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inventory Model representing catalog item definitions.
 */
class Model extends EloquentModel
{
    use HasFactory;

    protected $table = 'models';

    protected $with = ['type', 'brand', 'uom'];

    protected $fillable = [
        'number',
        'type_id',
        'subcategory_id',
        'brand_id',
        'uom_id',
        'name',
        'specification',
        'min_stock_threshold',
        'last_restock_at'
    ];

    protected $casts = [
        'last_restock_at' => 'datetime',
        'type_id' => 'integer',
        'brand_id' => 'integer',
        'uom_id' => 'integer',
        'min_stock_threshold' => 'integer',
    ];

    /**
     * The type classification for this model.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * Backward-compatibility relation for subcategory.
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function getTypeAttribute()
    {
        if ($this->relationLoaded('type')) {
            return $this->getRelation('type');
        }
        if ($this->relationLoaded('subcategory')) {
            return $this->getRelation('subcategory');
        }
        return $this->getRelationshipFromMethod('type');
    }

    public function getSubcategoryAttribute()
    {
        if ($this->relationLoaded('subcategory')) {
            return $this->getRelation('subcategory');
        }
        if ($this->relationLoaded('type')) {
            return $this->getRelation('type');
        }
        return $this->getRelationshipFromMethod('subcategory');
    }

    public function setSubcategoryIdAttribute($value): void
    {
        $this->attributes['type_id'] = $value;
    }

    public function getSubcategoryIdAttribute()
    {
        return $this->attributes['type_id'] ?? null;
    }

    /**
     * The brand manufacturer of this model.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The unit of measure (UOM) for this model.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    /**
     * Inventory batches (lots) received for this model.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'model_id');
    }

    /**
     * Inventory logs for this model.
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'model_id');
    }

    /**
     * Accessor indicating whether this model classification is consumable.
     */
    public function getIsConsumableAttribute(): bool
    {
        return (bool) ($this->type?->is_consumable ?? false);
    }

    /**
     * Accessor to ensure number attribute is properly trimmed.
     */
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
