<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'barang_id',
        'organizer_id',
        'vendor_id',
        'location_id',
        'floor_id',
        'room_id',
        'po_number',
        'date_of_receipt',
        'unit_price',
        'image_url',
    ];

    protected $casts = [
        'date_of_receipt' => 'datetime',
        'unit_price' => 'decimal:2',
    ];

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function getNumberAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
