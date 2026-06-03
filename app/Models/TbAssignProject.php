<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TbAssignProject extends Model
{
    use HasFactory;

    protected $table = 'tb_assign_projects';

    protected $fillable = [
        'npk',
        'no_project',
        'id_rbs',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * The user assigned to this project.
     * ADM_USER ||--o{ TB_ASSIGN_PROJECT : "assigned to"
     */
    public function admUser(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'npk', 'employee_id');
    }

    /**
     * The project this assignment belongs to.
     * TB_PROJECT ||--o{ TB_ASSIGN_PROJECT : "project details"
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TbProject::class, 'no_project', 'no_project');
    }

    /**
     * The RBS role for this assignment.
     * TB_RBS ||--o{ TB_ASSIGN_PROJECT : "assigned as"
     */
    public function rbs(): BelongsTo
    {
        return $this->belongsTo(TbRbs::class, 'id_rbs', 'id');
    }
}
