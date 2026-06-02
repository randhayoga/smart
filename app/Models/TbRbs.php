<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbRbs extends Model
{
    use HasFactory;

    protected $table = 'tb_rbs';

    protected $primaryKey = 'no_urut';

    protected $fillable = [
        'id',
        'name',
        'showing_name',
    ];

    /**
     * Assignment records for this RBS.
     * TB_RBS ||--o{ TB_ASSIGN_PROJECT : "assigned as"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'id_rbs', 'id');
    }
}
