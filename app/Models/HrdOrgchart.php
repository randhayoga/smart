<?php

namespace App\Models;

use App\Models\Request\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HRD Orgchart model representing organizational structure and department hierarchy.
 */
class HrdOrgchart extends Model
{
    use HasFactory;

    protected $connection = 'user_hris';

    protected $table = 'hrd_orgchart';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'id',
        'employee_id',
        'org_code',
        'org_name',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = ((int) static::max('id')) + 1;
            }
        });
    }

    /**
     * Mutator to handle both integer employee id (real HRIS schema) and employee string code.
     */
    public function setEmployeeIdAttribute($value): void
    {
        if ($value !== null && is_numeric($value)) {
            // If the value directly matches a User id, use it
            if (User::where('id', (int) $value)->exists()) {
                $this->attributes['employee_id'] = (int) $value;
                return;
            }
            // Otherwise, check if it's an employee code (employee_id column on User)
            $emp = User::where('employee_id', (string) $value)->first();
            if ($emp) {
                $this->attributes['employee_id'] = $emp->id;
                return;
            }
        }
        $this->attributes['employee_id'] = $value;
    }

    /**
     * The employee (manager) of this orgchart.
     * HRD_EMPLOYEE ||--o{ HRD_ORGCHART : "manages"
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'id');
    }

    /**
     * Employees belonging to this orgchart.
     * HRD_ORGCHART ||--o{ HRD_EMPLOYEE : "employs"
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'orgchart_id', 'id');
    }

    /**
     * Create a new model instance for a related model.
     * Ensures SMART application models route to the application's default connection.
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                if (str_starts_with(get_class($instance), 'App\\Models\\Request\\')) {
                    $instance->setConnection(config('database.default', 'SMART'));
                } else {
                    $instance->setConnection($this->connection);
                }
            }
        });
    }

    /**
     * Requests allocated to this orgchart (department).
     * HRD_ORGCHART ||--o{ REQUEST : "allocated to dept"
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'org_id', 'id');
    }
}
