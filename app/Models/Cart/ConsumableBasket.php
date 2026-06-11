<?php

namespace App\Models\Cart;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Master\Subcategory;

class ConsumableBasket extends Model
{
    protected $table = 'consumable_baskets';

    protected $fillable = [
        'user_id',
        'subcategory_id',
        'barang_id',
        'quantity',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }
}
