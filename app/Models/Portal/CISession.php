<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

/**
 * CodeIgniter Portal Session Model mapped to RE_PORTALDB.dbo.ci_sessions.
 */
class CISession extends Model
{
    /**
     * Database connection name.
     */
    protected $connection = 'reportal';

    /**
     * Database table name.
     */
    protected $table = 'RE_PORTALDB.dbo.ci_sessions';

    /**
     * Primary key.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];
}
