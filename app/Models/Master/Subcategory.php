<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];

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
        return $this->hasMany(\App\Models\Inventory\Barang::class);
    }

    public function getCodeAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
