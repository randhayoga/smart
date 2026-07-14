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
}
