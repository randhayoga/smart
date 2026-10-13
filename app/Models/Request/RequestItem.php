<?php

namespace App\Models\Request;

use App\Models\Inventory\Model;
use App\Models\Master\Type;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestItem extends EloquentModel
{
    protected $table = 'request_items';

    protected $fillable = [
        'request_id',
        'type_id',
        'model_id',
        'quantity_requested',
        'start_date',
        'end_date',
        'status',
        'subcategory_id',
        'barang_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(Model::class, 'model_id');
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Model::class, 'model_id');
    }

    public function getModelAttribute()
    {
        if ($this->relationLoaded('model')) {
            return $this->getRelation('model');
        }
        if ($this->relationLoaded('barang')) {
            return $this->getRelation('barang');
        }
        return $this->getRelationshipFromMethod('model');
    }

    public function getBarangAttribute()
    {
        if ($this->relationLoaded('barang')) {
            return $this->getRelation('barang');
        }
        if ($this->relationLoaded('model')) {
            return $this->getRelation('model');
        }
        return $this->getRelationshipFromMethod('barang');
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

    public function fulfillments(): HasMany
    {
        return $this->hasMany(RequestFulfillment::class, 'request_item_id');
    }

    public function setSubcategoryIdAttribute($value): void
    {
        $this->attributes['type_id'] = $value;
    }

    public function getSubcategoryIdAttribute()
    {
        return $this->attributes['type_id'] ?? null;
    }

    public function setBarangIdAttribute($value): void
    {
        $this->attributes['model_id'] = $value;
    }

    public function getBarangIdAttribute()
    {
        return $this->attributes['model_id'] ?? null;
    }

    /**
     * Accessor indicating whether this request item is consumable.
     */
    public function getIsConsumableAttribute(): bool
    {
        return (bool) ($this->model?->is_consumable ?? $this->type?->is_consumable ?? false);
    }
}
