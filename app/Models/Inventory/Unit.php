<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;

class Unit extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'Tersedia';
    public const STATUS_BORROWED = 'Dipinjam';
    public const STATUS_REPAIR = 'Perbaikan';
    public const STATUS_LOSS = 'Rusak';
    public const STATUS_LOST = 'Hilang';
    public const STATUS_INACTIVE = 'Tidak Aktif';

    protected $fillable = [
        'number',
        'lot_id',
        'location_id',
        'floor_id',
        'room_id',
        'status',
        'condition',
        'price',
        'image_url',
        'vehicle_registration',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
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

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function statusApprovals(): HasMany
    {
        return $this->hasMany(UnitStatusApproval::class);
    }

    public function getNumberAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }

    public function getIsVehicleAttribute(): bool
    {
        $this->loadMissing('lot.barang.subcategory.category');
        $lot = $this->lot;
        if ($lot && $lot->barang && $lot->barang->subcategory && $lot->barang->subcategory->category) {
            $catName = strtolower($lot->barang->subcategory->category->name);
            $subcatName = strtolower($lot->barang->subcategory->name);
            return str_contains($catName, 'kendaraan') || str_contains($subcatName, 'kendaraan') ||
                   str_contains($catName, 'mobil') || str_contains($subcatName, 'mobil') ||
                   str_contains($catName, 'motor') || str_contains($subcatName, 'motor');
        }
        return false;
    }
}
