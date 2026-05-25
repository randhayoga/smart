<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'floor_id',
    ];

    protected $casts = [
        'floor_id' => 'integer',
    ];

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }
}
