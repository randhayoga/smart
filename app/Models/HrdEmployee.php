<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrdEmployee extends Model
{
    use HasFactory;

    protected $table = 'hrd_employees';

    protected $fillable = [
        'orgchart_id',
        'employee_id',
        'employee_name',
        'email',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * The orgchart (department) this employee belongs to.
     * HRD_ORGCHART ||--o{ HRD_EMPLOYEE : "employs"
     */
    public function orgchart(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'orgchart_id');
    }

    /**
     * The adm_user credentials for this employee.
     * HRD_EMPLOYEE ||--|| ADM_USER : "credentials"
     */
    public function admUser(): HasOne
    {
        return $this->hasOne(AdmUser::class, 'employee_id', 'employee_id');
    }

    /**
     * Orgcharts managed by this employee.
     * HRD_EMPLOYEE ||--o{ HRD_ORGCHART : "manages"
     */
    public function managedOrgcharts(): HasMany
    {
        return $this->hasMany(HrdOrgchart::class, 'employee_id', 'employee_id');
    }

    public function getEmployeeIdAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
