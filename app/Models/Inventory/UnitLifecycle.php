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
        'status',
        'start_date',
        'end_date',
        'actor_id',
        'note',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'actor_id');
    }
}
