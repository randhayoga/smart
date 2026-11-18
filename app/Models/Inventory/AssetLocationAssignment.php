<?php

namespace App\Models\Inventory;

use App\Models\Master\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks active and historical location assignments with their assigned PIC user
 * for high-performance Start Time and End Time queries.
 */
class AssetLocationAssignment extends Model
{
    use HasFactory;

    protected $table = 'asset_location_assignments';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'location_id',
        'pic_user_id',
        'assigned_at',
        'unassigned_at',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'location_id' => 'integer',
        'pic_user_id' => 'integer',
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    /**
     * The asset associated with this location assignment.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * The location where the asset was assigned.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The employee (PIC) responsible for this location assignment.
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}
