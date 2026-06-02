<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbProject extends Model
{
    use HasFactory;

    protected $table = 'tb_projects';

    protected $fillable = [
        'no_project',
        'project_name',
        'client_id',
    ];

    /**
     * Assignment records for this project.
     * TB_PROJECT ||--o{ TB_ASSIGN_PROJECT : "project details"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'no_project', 'no_project');
    }

    /**
     * Requests allocated to this project.
     * TB_PROJECT ||--o{ REQUEST : "allocated to project"
     */
    public function requests(): HasMany
    {
        return $this->hasMany(\App\Models\Request\Request::class, 'project_id');
    }
}
