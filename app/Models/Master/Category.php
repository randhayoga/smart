<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master Category model representing top-level classifications (consumables vs non-consumables).
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Subcategories belonging to this category.
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    /**
     * Accessor to ensure code is properly trimmed.
     */
    public function getCodeAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
