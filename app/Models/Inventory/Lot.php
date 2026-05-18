<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;

class Lot extends Model
{
    use HasFactory;

    protected $table = 'lots';
    protected $guarded = [];

    protected $casts = [
        'date_of_receipt' => 'datetime',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'lot_id');
    }
}
