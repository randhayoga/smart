<?php

namespace App\Models\Master;

use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Request\RequestItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Type model representing item types under main categories.
 */
class Type extends Model
{
    use HasFactory;

    protected $table = 'types';

    protected $with = ['category'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_consumable',
        'category_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'is_consumable' => 'boolean',
    ];

    /**
     * Parent category for this type.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Models classified by this type.
     * TYPE ||--o{ MODEL : "classifies"
     */
    public function models(): HasMany
    {
        return $this->hasMany(InventoryModel::class, 'type_id');
    }

    /**
     * Backward-compatibility relation for barangs.
     */
    public function barangs(): HasMany
    {
        return $this->hasMany(InventoryModel::class, 'type_id');
    }

    public function getModelsAttribute()
    {
        if ($this->relationLoaded('models')) {
            return $this->getRelation('models');
        }
        if ($this->relationLoaded('barangs')) {
            return $this->getRelation('barangs');
        }
        return $this->getRelationshipFromMethod('models');
    }

    public function getBarangsAttribute()
    {
        if ($this->relationLoaded('barangs')) {
            return $this->getRelation('barangs');
        }
        if ($this->relationLoaded('models')) {
            return $this->getRelation('models');
        }
        return $this->getRelationshipFromMethod('barangs');
    }

    /**
     * Consumable basket entries referencing this type.
     */
    public function consumableBaskets(): HasMany
    {
        return $this->hasMany(ConsumableBasket::class, 'type_id');
    }

    /**
     * Asset basket entries referencing this type.
     */
    public function assetBaskets(): HasMany
    {
        return $this->hasMany(AssetBasket::class, 'type_id');
    }

    /**
     * Request items requesting this type.
     */
    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'type_id');
    }

    /**
     * Accessor to ensure code is properly trimmed.
     */
    public function getCodeAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
