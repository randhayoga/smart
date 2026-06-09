<?php

namespace App\Models\Request;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Inventory\Barang;

class RequestItem extends Model
{
    protected $table = 'request_items';

    protected $fillable = [
        'request_id',
        'barang_id',
        'quantity_requested',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class);
    }

    public function unitAssignments(): HasMany
    {
        return $this->hasMany(RequestUnitAssignment::class);
    }
}
