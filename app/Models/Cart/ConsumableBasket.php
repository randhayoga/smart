<?php

namespace App\Models\Cart;

use App\Models\User;
use App\Models\Inventory\Model;
use App\Models\Master\Type;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableBasket extends EloquentModel
{
    protected $table = 'consumable_baskets';

    protected $fillable = [
        'user_id',
        'type_id',
        'model_id',
        'quantity',
        'subcategory_id',
        'barang_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
