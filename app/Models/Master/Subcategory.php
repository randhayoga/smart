<?php

namespace App\Models\Master;

use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\Barang;
use App\Models\Request\RequestItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Subcategory model representing item subcategories under main categories.
 */
class Subcategory extends Model
{
    use HasFactory;

    protected $with = ['category'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];

    /**
     * Parent category for this subcategory.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Barangs classified by this subcategory.
     * SUBCATEGORY ||--o{ BARANG : "classifies"
     */
    public function barangs(): HasMany
    {
        return $this->hasMany(Barang::class);
    }

    /**
     * Consumable basket entries referencing this subcategory.
     */
    public function consumableBaskets(): HasMany
    {
        return $this->hasMany(ConsumableBasket::class);
    }

    /**
     * Asset basket entries referencing this subcategory.
     */
    public function assetBaskets(): HasMany
    {
        return $this->hasMany(AssetBasket::class);
    }

    /**
     * Request items requesting this subcategory.
     */
    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    /**
     * Accessor to ensure code is properly trimmed.
     */
    public function getCodeAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
