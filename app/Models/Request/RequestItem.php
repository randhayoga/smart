<?php

namespace App\Models\Request;

use App\Models\Inventory\Barang;
use App\Models\Master\Subcategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestItem extends Model
{
    protected $table = 'request_items';

    protected $fillable = [
        'request_id',
        'subcategory_id',
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

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
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
