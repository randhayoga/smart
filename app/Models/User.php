<?php

namespace App\Models;

use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\InventoryLog;
use App\Models\Request\Request;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use App\Models\TbAssignProject;
use App\Models\HrdOrgchart;
use App\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * Application User model representing user authentication and personnel records from USER_HRIS.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    protected $connection = 'user_hris';

    protected $table = 'hrd_employee';

    protected $primaryKey = 'id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'orgchart_id',
        'employee_id',
        'employee_name',
        'name',
        'username',
        'email',
        'active',
    ];

    protected $appends = [
        'username',
        'name',
        'role',
        'is_admin',
        'is_superadmin',
        'org_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'integer',
        'active' => 'boolean',
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
     * Get the password attribute for local development authentication.
     * Checks USER_HRIS.dbo.adm_user (MD5), direct model attribute, or local/testing fallback.
     */
    public function getAuthPassword(): ?string
    {
        if ($this->relationLoaded('admUser') && $this->admUser && !empty($this->admUser->password)) {
            return $this->admUser->password;
        }

        if (!empty($this->attributes['password'])) {
            return $this->attributes['password'];
        }

        if (!empty($this->employee_id)) {
            $admPassword = AdmUser::where('employee_id', (string) $this->employee_id)
                ->orWhere('login_name', (string) $this->employee_id)
                ->value('password');
            if (!empty($admPassword)) {
                return $admPassword;
            }
        }

        if (app()->environment('local', 'testing')) {
            return md5(config('auth.dev_password', 'password'));
        }

        return null;
    }

    /**
     * Password mutator for local testing or credentials assignment.
     */
    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = $value;
    }

    /**
     * Password accessor returning the effective authentication password hash.
     */
    public function getPasswordAttribute(): ?string
    {
        return $this->getAuthPassword();
    }

    /**
     * Disable remember token persistence since hrd_employee table has no remember_token column.
     */
    public function setRememberToken($value): void
    {
        // Do nothing
    }

    /**
     * Get the effective organization code for the IFS department.
     * In non-production environments (local, dev, testing), defaults to 'TEST-DEPT'
     * so that notifications and IFS approvals are routed to the test manager
     * (Departemen Fasilitas Uji Coba) instead of production personnel.
     */
    public static function getIfsOrgCode(): string
    {
        return app()->isProduction() ? 'IFS' : 'TEST-DEPT';
    }

    protected ?string $cachedRoleName = null;

    /**
     * Request-level memoized lookup sets for O(1) dynamic role & org resolution without N+1 queries.
     */
    protected static ?array $memoizedIfsManagerIds = null;
    protected static ?array $memoizedDeptManagerIds = null;
    protected static ?array $memoizedLatestPmNpks = null;
    protected static ?array $memoizedOrgNamesById = null;
    protected static ?string $memoizedIfsOrgCode = null;

    /**
     * Clear memoized role and orgchart lookup caches.
     */
    public static function clearRoleLookupCache(): void
    {
        static::$memoizedIfsManagerIds = null;
        static::$memoizedDeptManagerIds = null;
        static::$memoizedLatestPmNpks = null;
        static::$memoizedOrgNamesById = null;
        static::$memoizedIfsOrgCode = null;
    }

    /**
     * Ensure HRD orgchart lookup maps (IFS manager IDs, Department manager IDs, and Org names) are loaded once.
     */
    protected static function ensureOrgchartLookupLoaded(): void
    {
        $ifsOrgCode = static::getIfsOrgCode();
        if (static::$memoizedIfsManagerIds !== null && static::$memoizedDeptManagerIds !== null && static::$memoizedIfsOrgCode === $ifsOrgCode) {
            return;
        }

        static::$memoizedIfsOrgCode = $ifsOrgCode;
        static::$memoizedIfsManagerIds = [];
        static::$memoizedDeptManagerIds = [];
        static::$memoizedOrgNamesById = [];

        $rows = HrdOrgchart::query()
            ->toBase()
            ->select(['id', 'employee_id', 'org_code', 'org_name'])
            ->get();

        foreach ($rows as $row) {
            if ($row->id !== null) {
                static::$memoizedOrgNamesById[(int) $row->id] = $row->org_name;
            }
            if ($row->employee_id !== null && $row->employee_id !== '') {
                $empKey = trim((string) $row->employee_id);
                static::$memoizedDeptManagerIds[$empKey] = true;
                if (is_numeric($empKey)) {
                    static::$memoizedDeptManagerIds[(string) (int) $empKey] = true;
                }
                if ((string) $row->org_code === $ifsOrgCode) {
                    static::$memoizedIfsManagerIds[$empKey] = true;
                    if (is_numeric($empKey)) {
                        static::$memoizedIfsManagerIds[(string) (int) $empKey] = true;
                    }
                }
            }
        }
    }

    /**
     * Ensure active Project Manager (P2211) NPK lookup set is loaded once.
     */
    protected static function ensureLatestPmLookupLoaded(): void
    {
        if (static::$memoizedLatestPmNpks !== null) {
            return;
        }

        static::$memoizedLatestPmNpks = [];

        // Fetch the newest P2211 assignment per project in a single SQL query using ROW_NUMBER()
        $rows = \Illuminate\Support\Facades\DB::connection('reportal')
            ->table('tb_assign_project')
            ->selectRaw('no_project, npk, ROW_NUMBER() OVER (PARTITION BY no_project ORDER BY start_date DESC, id_assign DESC) as rn')
            ->where('id_rbs', 'P2211')
            ->whereNotNull('no_project')
            ->get();

        foreach ($rows as $row) {
            if ((int) $row->rn === 1 && !empty($row->npk)) {
                static::$memoizedLatestPmNpks[trim((string) $row->npk)] = true;
            }
        }
    }

    /**
     * Preload assigned roles and dynamic role lookup sets for a collection of users in bulk.
     */
    public static function preloadRolesFor(iterable $users): void
    {
        $userList = [];
        $userIds = [];
        foreach ($users as $user) {
            if ($user instanceof self) {
                $userList[] = $user;
                if ($user->id !== null) {
                    $userIds[] = (int) $user->id;
                }
            }
        }

        if (empty($userList)) {
            return;
        }

        static::ensureOrgchartLookupLoaded();
        static::ensureLatestPmLookupLoaded();

        $rolesByUserId = [];
        if (!empty($userIds)) {
            try {
                $assignedRows = \Illuminate\Support\Facades\DB::connection(config('database.default', 'SMART'))
                    ->table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->whereIn('user_roles.user_id', array_values(array_unique($userIds)))
                    ->select(['user_roles.user_id', 'roles.name'])
                    ->get();

                foreach ($assignedRows as $row) {
                    $rolesByUserId[(int) $row->user_id][] = $row->name;
                }
            } catch (\Throwable $e) {
                // Ignore if user_roles table not migrated
            }
        }

        foreach ($userList as $user) {
            if ($user->cachedRoleName !== null) {
                continue;
            }
            $uid = (int) $user->id;
            if (!empty($rolesByUserId[$uid])) {
                $roleNames = $rolesByUserId[$uid];
                foreach (['superadmin', 'admin', 'ifs_manager', 'manager', 'user'] as $priorityRole) {
                    if (in_array($priorityRole, $roleNames, true)) {
                        $user->cachedRoleName = $priorityRole;
                        break;
                    }
                }
                if ($user->cachedRoleName === null) {
                    $user->cachedRoleName = $roleNames[0];
                }
                continue;
            }

            // Compute fallback role in O(1) memory without hitting user_roles per user
            $user->cachedRoleName = $user->resolveDynamicFallbackRole();
        }
    }

    /**
     * Resolve fallback role from hardcoded admin IDs and memoized HRIS/Reportal manager sets in O(1) time.
     */
    public function resolveDynamicFallbackRole(): string
    {
        $empId = trim((string) ($this->employee_id ?? ''));
        if ($empId === '265656') {
            return 'superadmin';
        }

        $admins = ['255578'];
        if (in_array($empId, $admins, true) || ((app()->runningUnitTests() || app()->environment('testing')) && !config('app.disable_test_admin_bypass'))) {
            return 'admin';
        }

        static::ensureOrgchartLookupLoaded();

        $idStr = (string) ($this->id ?? '');
        if (($idStr !== '' && isset(static::$memoizedIfsManagerIds[$idStr])) || ($empId !== '' && isset(static::$memoizedIfsManagerIds[$empId]))) {
            return 'ifs_manager';
        }

        if (($idStr !== '' && isset(static::$memoizedDeptManagerIds[$idStr])) || ($empId !== '' && isset(static::$memoizedDeptManagerIds[$empId]))) {
            return 'manager';
        }

        static::ensureLatestPmLookupLoaded();
        if ($empId !== '' && isset(static::$memoizedLatestPmNpks[$empId])) {
            return 'manager';
        }

        return 'user';
    }

    /**
     * Refresh the model and clear cached attributes.
     */
    public function refresh()
    {
        $this->cachedRoleName = null;
        $this->cachedRoleNames = null;
        $this->cachedPermissionNames = null;
        return parent::refresh();
    }

    /**
     * Get the dynamic role of the user.
     */
    public function getRoleAttribute(): string
    {
        if ($this->cachedRoleName !== null) {
            return $this->cachedRoleName;
        }

        // 1. Check if roles relation is already eager-loaded
        if ($this->relationLoaded('roles')) {
            $roleNames = $this->roles->pluck('name')->all();
            foreach (['superadmin', 'admin', 'ifs_manager', 'manager', 'user'] as $priorityRole) {
                if (in_array($priorityRole, $roleNames, true)) {
                    return $this->cachedRoleName = $priorityRole;
                }
            }
            if (!empty($roleNames)) {
                return $this->cachedRoleName = $roleNames[0];
            }
        }

        // 2. Check hardcoded superadmin and admin list
        $empId = (string) ($this->employee_id ?? '');
        if ($empId === '265656') {
            return $this->cachedRoleName = 'superadmin';
        }

        $admins = ['255578'];
        if (in_array($empId, $admins, true) || ((app()->runningUnitTests() || app()->environment('testing')) && !config('app.disable_test_admin_bypass'))) {
            return $this->cachedRoleName = 'admin';
        }

        // 3. Query assigned roles from SMART database if model is persisted and roles were not preloaded
        if ($this->exists && !empty($this->id) && !$this->relationLoaded('roles')) {
            try {
                $roleNames = $this->roles()->pluck('name')->all();
                if (!empty($roleNames)) {
                    foreach (['superadmin', 'admin', 'ifs_manager', 'manager', 'user'] as $priorityRole) {
                        if (in_array($priorityRole, $roleNames, true)) {
                            return $this->cachedRoleName = $priorityRole;
                        }
                    }
                    return $this->cachedRoleName = $roleNames[0];
                }
            } catch (\Throwable $e) {
                // Fall back to legacy dynamic calculation on DB error
            }
        }

        // 4. Dynamic fallback calculation using memoized O(1) lookup sets
        return $this->cachedRoleName = $this->resolveDynamicFallbackRole();
    }

    /**
     * Check if the user is an admin.
     */
    public function getIsAdminAttribute(): bool
    {
        return in_array($this->role, ['superadmin', 'admin', 'ifs_manager'], true);
    }

    /**
     * Check if the user is a superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin' || (string) ($this->employee_id ?? '') === '265656';
    }

    /**
     * Check if the user is a superadmin accessor.
     */
    public function getIsSuperadminAttribute(): bool
    {
        return $this->isSuperadmin();
    }

    /**
     * Get users by dynamic role(s) using optimized queries.
     *
     * @param string|array<string> $roles 'admin' | 'ifs_manager' | 'manager' | 'user'
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function getUsersByRole(string|array $roles)
    {
        $roles = (array) $roles;
        $adminIds = ['255578'];
        $ifsOrgCode = static::getIfsOrgCode();

        $targetEmployeeIds = collect();
        $includeAllRegularUsers = false;

        $ifsEmployeeIds = null;
        $getIfsEmployeeIds = function () use (&$ifsEmployeeIds, $ifsOrgCode) {
            if ($ifsEmployeeIds === null) {
                $ifsOrgs = HrdOrgchart::where('org_code', $ifsOrgCode)->with('manager')->get();
                $ifsEmployeeIds = [];
                foreach ($ifsOrgs as $org) {
                    if ($org->manager?->employee_id) {
                        $ifsEmployeeIds[] = $org->manager->employee_id;
                    } elseif ($org->employee_id) {
                        $empId = static::where('id', $org->employee_id)->value('employee_id') ?? (string) $org->employee_id;
                        $ifsEmployeeIds[] = $empId;
                    }
                }
            }
            return $ifsEmployeeIds;
        };

        $managerEmployeeIds = null;
        $getManagerEmployeeIds = function () use (&$managerEmployeeIds, $ifsOrgCode) {
            if ($managerEmployeeIds === null) {
                // Dept managers from HRD_ORGCHART (except IFS and TEST-DEPT in local)
                $orgs = HrdOrgchart::whereNotNull('employee_id')
                    ->where(function ($q) use ($ifsOrgCode) {
                        $q->whereNotIn('org_code', array_unique(['IFS', $ifsOrgCode]))
                            ->orWhereNull('org_code');
                    })
                    ->with('manager')
                    ->get();

                $deptManagerIds = [];
                foreach ($orgs as $org) {
                    if ($org->manager?->employee_id) {
                        $deptManagerIds[] = $org->manager->employee_id;
                    } elseif ($org->employee_id) {
                        $empId = static::where('id', $org->employee_id)->value('employee_id') ?? (string) $org->employee_id;
                        $deptManagerIds[] = $empId;
                    }
                }

                // Project managers with id_rbs = P2211 (only newest for each project's no_project)
                $projectManagerIds = TbAssignProject::where('id_rbs', 'P2211')
                    ->orderByDesc('start_date')
                    ->orderByDesc('id_assign')
                    ->get(['no_project', 'npk'])
                    ->unique('no_project')
                    ->pluck('npk')
                    ->filter()
                    ->toArray();

                $managerEmployeeIds = array_values(array_unique(array_merge($deptManagerIds, $projectManagerIds)));
            }
            return $managerEmployeeIds;
        };

        foreach ($roles as $role) {
            switch ($role) {
                case 'superadmin':
                case 'admin':
                    $targetEmployeeIds = $targetEmployeeIds->merge($adminIds);
                    break;
                case 'ifs_manager':
                    $targetEmployeeIds = $targetEmployeeIds->merge($getIfsEmployeeIds());
                    break;
                case 'manager':
                    $targetEmployeeIds = $targetEmployeeIds->merge($getManagerEmployeeIds());
                    break;
                case 'user':
                    $includeAllRegularUsers = true;
                    break;
            }
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('user_roles')) {
                $dbUserIds = \Illuminate\Support\Facades\DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->whereIn('roles.name', $roles)
                    ->pluck('user_roles.user_id')
                    ->all();
                if (!empty($dbUserIds)) {
                    $dbEmpIds = static::whereIn('id', $dbUserIds)->pluck('employee_id')->all();
                    $targetEmployeeIds = $targetEmployeeIds->merge($dbEmpIds);
                }
            }
        } catch (\Throwable $e) {
            // Ignore schema / connection error during bootstrapping
        }

        if ($includeAllRegularUsers) {
            $excludeIds = array_unique(array_merge(
                $adminIds,
                $getIfsEmployeeIds(),
                $getManagerEmployeeIds()
            ));
            return static::whereNotIn('employee_id', $excludeIds)->get();
        }

        if (!app()->isProduction() && in_array('ifs_manager', $roles, true)) {
            $targetEmployeeIds = $targetEmployeeIds->filter(fn($id) => (string) $id !== '033340');
        }

        $uniqueIds = $targetEmployeeIds->filter()->unique()->values()->all();

        if (empty($uniqueIds)) {
            return static::whereRaw('1 = 0')->get();
        }

        return static::whereIn('employee_id', $uniqueIds)->get();
    }

    /**
     * Create a custom Eloquent builder for User to alias username to employee_id.
     */
    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if (is_string($column) && in_array($column, ['username', 'users.username', 'hrd_employee.username'], true)) {
                    $column = 'employee_id';
                }
                return parent::where($column, $operator, $value, $boolean);
            }

            public function whereIn($column, $values, $boolean = 'and', $not = false)
            {
                if (is_string($column) && in_array($column, ['username', 'users.username', 'hrd_employee.username'], true)) {
                    $column = 'employee_id';
                }
                return parent::whereIn($column, $values, $boolean, $not);
            }

            public function whereNotIn($column, $values, $boolean = 'and')
            {
                if (is_string($column) && in_array($column, ['username', 'users.username', 'hrd_employee.username'], true)) {
                    $column = 'employee_id';
                }
                return parent::whereNotIn($column, $values, $boolean);
            }
        };
    }

    /**
     * Accessor for username (mapped to employee_id).
     */
    public function getUsernameAttribute(): ?string
    {
        return $this->attributes['employee_id'] ?? null;
    }

    /**
     * Mutator for username.
     */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['employee_id'] = $value;
    }

    /**
     * Accessor for name (mapped to employee_name).
     */
    public function getNameAttribute(): ?string
    {
        return $this->attributes['employee_name'] ?? null;
    }

    /**
     * Mutator for name.
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['employee_name'] = $value;
    }

    /**
     * Accessor for email.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->attributes['email'] ?? null;
    }

    /**
     * Mutator for email.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value;
    }

    /**
     * Accessor to ensure employee_id is properly trimmed.
     */
    public function getEmployeeIdAttribute($value): ?string
    {
        return $value !== null ? trim($value) : ($this->attributes['employee_id'] ?? null);
    }

    /**
     * Get the organization name of the user without triggering N+1 lazy loads.
     */
    public function getOrgNameAttribute(): ?string
    {
        if (array_key_exists('org_name', $this->attributes)) {
            return $this->attributes['org_name'];
        }

        if ($this->relationLoaded('orgchart')) {
            return $this->orgchart?->org_name;
        }

        $orgchartId = $this->attributes['orgchart_id'] ?? null;
        if ($orgchartId === null || $orgchartId === '') {
            return null;
        }

        static::ensureOrgchartLookupLoaded();

        return static::$memoizedOrgNamesById[(int) $orgchartId] ?? null;
    }

    /**
     * Self accessor for hrdEmployee to support direct access.
     */
    public function getHrdEmployeeAttribute(): self
    {
        return $this;
    }

    /**
     * Self relationship for hrdEmployee to support eager loading.
     */
    public function hrdEmployee(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id', 'id');
    }

    /**
     * The credentials record linked to this employee in USER_HRIS.
     */
    public function admUser(): HasOne
    {
        return $this->hasOne(AdmUser::class, 'employee_id', 'employee_id');
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email;
    }

    /**
     * Scope for active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * The orgchart (department) this employee belongs to.
     */
    public function orgchart(): BelongsTo
    {
        return $this->belongsTo(HrdOrgchart::class, 'orgchart_id', 'id');
    }

    /**
     * Orgcharts managed by this employee.
     */
    public function managedOrgcharts(): HasMany
    {
        return $this->hasMany(HrdOrgchart::class, 'employee_id', 'id');
    }

    /**
     * Project assignments for this user.
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'npk', 'employee_id');
    }

    /**
     * Consumable basket items for this user.
     */
    public function consumableBaskets(): HasMany
    {
        return $this->hasMany(ConsumableBasket::class, 'user_id', 'id');
    }

    /**
     * Asset basket items for this user.
     */
    public function assetBaskets(): HasMany
    {
        return $this->hasMany(AssetBasket::class, 'user_id', 'id');
    }

    /**
     * Requests submitted by this user.
     */
    public function submittedRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'user_id', 'id');
    }

    /**
     * Requests assigned to this user for approval.
     */
    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(Request::class, 'approver_id', 'id');
    }

    /**
     * Asset deletion requests made by this user.
     */
    public function assetDeletionRequests(): HasMany
    {
        return $this->hasMany(AssetDeletionApproval::class, 'requester_id', 'id');
    }

    /**
     * Asset deletion decisions made by this user.
     */
    public function assetDeletionDecisions(): HasMany
    {
        return $this->hasMany(AssetDeletionApproval::class, 'approver_id', 'id');
    }

    /**
     * Inventory logs actioned by this user.
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'user_id', 'id');
    }

    /**
     * Request approvals decided by this user.
     */
    public function requestApprovals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'approver_id', 'id');
    }

    /**
     * Request admin confirmations acted on by this user.
     */
    public function requestAdminConfirmations(): HasMany
    {
        return $this->hasMany(RequestAdminConfirmation::class, 'admin_id', 'id');
    }

    /**
     * Request status logs actioned by this user.
     */
    public function requestStatusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class, 'changed_by', 'id');
    }

    /**
     * Create a new model instance for a related model.
     * Ensures SMART application models and notifications route to the application's default connection.
     */
    protected function newRelatedInstance($class)
    {
        return tap(new $class, function ($instance) {
            if (! $instance->getConnectionName()) {
                if (str_starts_with(get_class($instance), 'App\\Models\\Cart\\')
                    || str_starts_with(get_class($instance), 'App\\Models\\Inventory\\')
                    || str_starts_with(get_class($instance), 'App\\Models\\Master\\')
                    || str_starts_with(get_class($instance), 'App\\Models\\Request\\')
                    || $instance instanceof \Illuminate\Notifications\DatabaseNotification) {
                    $instance->setConnection(config('database.default', 'SMART'));
                } else {
                    $instance->setConnection($this->connection);
                }
            }
        });
    }
}
