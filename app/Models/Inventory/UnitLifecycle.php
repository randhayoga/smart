<?php

namespace App\Models\Inventory;

use App\Models\AdmUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitLifecycle extends Model
{
    use HasFactory;

    protected $table = 'unit_lifecycles';

    public $timestamps = false;

    protected $fillable = [
        'unit_id',
        'action_type',
        'status',
        'condition',
        'location_id',
        'floor_id',
        'room_id',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Master\Location::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Master\Floor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Master\Room::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'actor_id');
    }

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
