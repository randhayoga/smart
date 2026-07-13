<?php

namespace App\Models;

use App\Models\Request\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrdOrgchart extends Model
{
    use HasFactory;

    protected $table = 'hrd_orgcharts';

    protected $fillable = [
        'employee_id',
        'org_code',
        'org_name',
    ];

    /**
     * The employee (manager) of this orgchart.
     * HRD_EMPLOYEE ||--o{ HRD_ORGCHART : "manages"
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(HrdEmployee::class, 'employee_id', 'employee_id');
    }

    /**
     * Employees belonging to this orgchart.
     * HRD_ORGCHART ||--o{ HRD_EMPLOYEE : "employs"
     */
    public function employees(): HasMany
    {
        return $this->hasMany(HrdEmployee::class, 'orgchart_id');
    }

    /**
     * Requests allocated to this orgchart (department).
     * HRD_ORGCHART ||--o{ REQUEST : "allocated to dept"
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'org_id');
    }
}
