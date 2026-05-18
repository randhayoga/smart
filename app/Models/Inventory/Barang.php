<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Subcategory;
use App\Models\Master\Brand;
use App\Models\Master\Uom;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barangs';
    protected $guarded = [];

    protected $casts = [
        'last_restock_at' => 'datetime',
    ];

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class, 'subcategory_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function lots()
    {
        return $this->hasMany(Lot::class, 'barang_id');
    }
}
