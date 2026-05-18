<?php

namespace App\Models\Request;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestAdminConfirmation extends Model
{
    use HasFactory;

    protected $table = 'request_admin_confirmations';
    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
