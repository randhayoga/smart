<?php

namespace App\Models\Request;

use App\Models\Inventory\Barang;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestItem extends Model
{
    use HasFactory;

    protected $table = 'request_items';
    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function assignments()
    {
        return $this->hasMany(RequestUnitAssignment::class, 'request_item_id');
    }
}
