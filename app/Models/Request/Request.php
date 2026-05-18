<?php

namespace App\Models\Request;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory;

    protected $table = 'requests';
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    public function approvals()
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }

    public function confirmations()
    {
        return $this->hasMany(RequestAdminConfirmation::class, 'request_id');
    }

    public function handover()
    {
        return $this->hasOne(RequestHandover::class, 'request_id');
    }

    public function return()
    {
        return $this->hasOne(RequestReturn::class, 'request_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(RequestStatusLog::class, 'request_id');
    }
}
