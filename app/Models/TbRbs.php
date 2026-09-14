<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role Breakdown Structure (RBS) model representing project role definitions.
 */
class TbRbs extends Model
{
    use HasFactory;

    protected $connection = 'reportal';

    protected $table = 'tb_rbs';

    protected $primaryKey = 'no_urut';

    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'no_urut',
        'id',
        'name',
        'showing_name',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->no_urut)) {
                $model->no_urut = ((int) static::max('no_urut')) + 1;
            }
        });
    }

    /**
     * Assignment records for this RBS.
     * TB_RBS ||--o{ TB_ASSIGN_PROJECT : "assigned as"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'ID_RBS', 'id');
    }
}
