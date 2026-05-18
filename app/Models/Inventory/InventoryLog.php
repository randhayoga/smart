<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $table = 'inventory_logs';
    protected $guarded = [];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
