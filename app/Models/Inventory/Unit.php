<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use App\Models\User;

class Unit extends Model
{
    use HasFactory;

    // Asset statuses (Consumables do not get individual UNIT records)
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BORROWED = 'borrowed';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BROKEN = 'broken';

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
        'user_id'
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
