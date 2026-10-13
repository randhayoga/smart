<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asset Lifecycle model tracking historical movements, condition transitions, and status changes of assets.
 */
class AssetLifecycle extends Model
{
    use HasFactory;

    protected $table = 'asset_lifecycles';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'unit_id',
        'action_type',
        'status',
        'condition',
        'location_id',
        'start_date',
        'end_date',
        'actor_id',
        'note',
        'previous_state',
        'new_state',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    /**
     * The asset associated with this lifecycle entry.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function setUnitIdAttribute($value): void
    {
        $this->attributes['asset_id'] = $value;
    }

    public function getUnitIdAttribute()
    {
        return $this->attributes['asset_id'] ?? null;
    }

    /**
     * The location during this lifecycle period.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Master\Location::class);
    }

    /**
     * The actor/user who triggered this lifecycle change.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Accessor to get a human-readable formatted duration string (Indonesian).
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->start_date || !$this->end_date) {
            return '-';
        }

        $days = (int) floor($this->start_date->diffInDays($this->end_date));
        if ($days < 30) {
            return "{$days} hari";
        }

        $years = (int) floor($days / 365);
        $remDaysAfterYears = $days % 365;
        $months = (int) floor($remDaysAfterYears / 30);
        $remainingDays = $remDaysAfterYears % 30;

        $parts = [];
        if ($years > 0) {
            $parts[] = "{$years} tahun";
        }
        if ($months > 0) {
            $parts[] = "{$months} bulan";
        }
        if ($remainingDays > 0 || empty($parts)) {
            $parts[] = "{$remainingDays} hari";
        }

        return implode(' ', $parts);
    }
}
