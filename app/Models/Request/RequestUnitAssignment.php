<?php

namespace App\Models\Request;

use App\Models\Inventory\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestUnitAssignment extends Model
{
    use HasFactory;

    protected $table = 'request_unit_assignments';
    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function requestItem()
    {
        return $this->belongsTo(RequestItem::class, 'request_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
