<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * HRD Employee model representing personnel records and organizational membership.
 */
class HrdEmployee extends Model
{
    use HasFactory;

    protected $connection = 'user_hris';

    protected $table = 'hrd_employee';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id',
        'orgchart_id',
        'employee_id',
        'employee_name',
        'email',
        'active',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = ((int) static::max('id')) + 1;
            }
        });
    }

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * The orgchart (department) this employee belongs to.
     * HRD_ORGCHART ||--o{ HRD_EMPLOYEE : "employs"
     */
    public function orgchart(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'orgchart_id', 'id');
    }

    /**
     * The adm_user credentials for this employee.
     * HRD_EMPLOYEE ||--|| ADM_USER : "credentials"
     */
    public function admUser(): HasOne
    {
        return $this->hasOne(AdmUser::class, 'username', 'employee_id');
    }

    /**
     * Orgcharts managed by this employee.
     * HRD_EMPLOYEE ||--o{ HRD_ORGCHART : "manages"
     */
    public function managedOrgcharts(): HasMany
    {
        return $this->hasMany(HrdOrgchart::class, 'employee_id', 'id');
    }

    /**
     * Accessor to ensure employee_id is properly trimmed.
     */
    public function getEmployeeIdAttribute($value)
    {
        return $value !== null ? trim($value) : null;
    }
}
