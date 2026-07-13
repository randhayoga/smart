<?php

namespace App\Models\Cart;

use App\Models\AdmUser;
use App\Models\Inventory\Barang;
use App\Models\Master\Subcategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetBasket extends Model
{
    protected $table = 'asset_baskets';

    protected $fillable = [
        'user_id',
        'subcategory_id',
        'barang_id',
        'quantity',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
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
