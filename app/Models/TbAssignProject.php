<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Project Assignment model linking employees to specific projects and RBS roles.
 */
class TbAssignProject extends Model
{
    use HasFactory;

    protected $connection = 'reportal';

    protected $table = 'tb_assign_project';

    protected $primaryKey = 'ID_ASSIGN';

    public $incrementing = true;

    protected $fillable = [
        'ID_ASSIGN',
        'id_assign',
        'id',
        'npk',
        'NPK',
        'no_project',
        'NO_PROJECT',
        'id_rbs',
        'ID_RBS',
        'start_date',
        'START_DATE',
        'end_date',
        'END_DATE',
    ];

    public function getIdAttribute(): mixed
    {
        return $this->attributes['ID_ASSIGN'] ?? $this->attributes['id_assign'] ?? $this->attributes['id'] ?? null;
    }

    public function setIdAttribute($value): void
    {
        $this->attributes['ID_ASSIGN'] = $value;
    }

    public function getIdAssignAttribute(): mixed
    {
        return $this->attributes['ID_ASSIGN'] ?? $this->attributes['id_assign'] ?? null;
    }

    public function setIdAssignAttribute($value): void
    {
        $this->attributes['ID_ASSIGN'] = $value;
    }

    public function getNpkAttribute(): ?string
    {
        return $this->attributes['NPK'] ?? $this->attributes['npk'] ?? null;
    }

    public function setNpkAttribute($value): void
    {
        $this->attributes['NPK'] = $value;
    }

    public function getNoProjectAttribute(): ?string
    {
        return $this->attributes['NO_PROJECT'] ?? $this->attributes['no_project'] ?? null;
    }

    public function setNoProjectAttribute($value): void
    {
        $this->attributes['NO_PROJECT'] = $value;
    }

    public function getIdRbsAttribute(): ?string
    {
        return $this->attributes['ID_RBS'] ?? $this->attributes['id_rbs'] ?? null;
    }

    public function setIdRbsAttribute($value): void
    {
        $this->attributes['ID_RBS'] = $value;
    }

    public function getStartDateAttribute(): mixed
    {
        $val = $this->attributes['START_DATE'] ?? $this->attributes['start_date'] ?? null;
        return $val ? $this->asDateTime($val) : null;
    }

    public function setStartDateAttribute($value): void
    {
        $this->attributes['START_DATE'] = $value ? $this->fromDateTime($value) : null;
    }

    public function getEndDateAttribute(): mixed
    {
        $val = $this->attributes['END_DATE'] ?? $this->attributes['end_date'] ?? null;
        return $val ? $this->asDateTime($val) : null;
    }

    public function setEndDateAttribute($value): void
    {
        $this->attributes['END_DATE'] = $value ? $this->fromDateTime($value) : null;
    }

    /**
     * The user assigned to this project.
     * ADM_USER ||--o{ TB_ASSIGN_PROJECT : "assigned to"
     */
    public function admUser(): BelongsTo
    {
        return $this->belongsTo(AdmUser::class, 'NPK', 'username');
    }

    /**
     * The project this assignment belongs to.
     * TB_PROJECT ||--o{ TB_ASSIGN_PROJECT : "project details"
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(TbProject::class, 'NO_PROJECT', 'no_project');
    }

    /**
     * The RBS role for this assignment.
     * TB_RBS ||--o{ TB_ASSIGN_PROJECT : "assigned as"
     */
    public function rbs(): BelongsTo
    {
        return $this->belongsTo(TbRbs::class, 'ID_RBS', 'id');
    }
}
