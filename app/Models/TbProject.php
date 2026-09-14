<?php

namespace App\Models;

use App\Models\Request\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Project model representing internal and client projects.
 */
class TbProject extends Model
{
    use HasFactory;

    protected $connection = 'reportal';

    protected $table = 'tb_project';

    protected $primaryKey = 'id_project';

    protected $fillable = [
        'id_project',
        'no_project',
        'project_name',
        'client_id',
        'ClientID',
    ];

    protected $appends = [
        'id',
        'client_id',
    ];

    public function getIdAttribute(): mixed
    {
        return $this->attributes['id_project'] ?? $this->attributes['id'] ?? null;
    }

    public function setIdAttribute($value): void
    {
        $this->attributes['id_project'] = $value;
    }

    public function getClientIdAttribute(): ?string
    {
        return $this->attributes['ClientID'] ?? $this->attributes['client_id'] ?? null;
    }

    public function setClientIdAttribute($value): void
    {
        $this->attributes['ClientID'] = $value;
    }

    /**
     * Assignment records for this project.
     * TB_PROJECT ||--o{ TB_ASSIGN_PROJECT : "project details"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'no_project', 'no_project');
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
     * Requests allocated to this project.
     * TB_PROJECT ||--o{ REQUEST : "allocated to project"
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'project_id', 'id_project');
    }
}
