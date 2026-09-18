<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AdmUser model mapped to USER_HRIS.dbo.adm_user containing corporate login credentials.
 */
class AdmUser extends Model
{
    use HasFactory;

    protected $connection = 'user_hris';


    protected $table = 'adm_user';

    protected $primaryKey = 'id_adm_user';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_adm_user',
        'login_name',
        'name',
        'password',
        'employee_id',
        'active',
        'login_ldap',
        'flag_external',
        'remember_token',
        'ip_address',
    ];

    protected $casts = [
        'id_adm_user' => 'integer',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id_adm_user)) {
                $model->id_adm_user = ((int) static::max('id_adm_user')) + 1;
            }
        });
    }

    /**
     * The employee profile linked to this credential account.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
}
