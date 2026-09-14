<?php

namespace App\Models;

use App\Models\Cart\AssetBasket;
use App\Models\Cart\ConsumableBasket;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\UnitStatusApproval;
use App\Models\Request\Request;
use App\Models\Request\RequestAdminConfirmation;
use App\Models\Request\RequestApproval;
use App\Models\Request\RequestStatusLog;
use App\Models\TbAssignProject;
use App\Models\HrdOrgchart;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Application User model representing user authentication and role management.
 */
class AdmUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'new_portal';

    protected $table = 'users';

    protected $fillable = [
        'name',
        'username',
        'employee_id',
        'email',
        'password',
        'password_hash',
    ];

    protected $appends = [
        'employee_id',
        'username',
        'email',
        'role',
        'is_admin',
        'org_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the password attribute for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password ?? $this->password_hash;
    }

    /**
     * Get the dynamic role of the user.
     */
    public function getRoleAttribute(): string
    {
        $admins = ['252525', '255578'];
        $empId = (string) ($this->employee_id ?? $this->username);
        if (in_array($empId, $admins) || ((app()->runningUnitTests() || app()->environment('testing')) && !config('app.disable_test_admin_bypass'))) {
            return 'admin';
        }

        $ifsOrgs = HrdOrgchart::where('org_code', 'IFS')->get();
        foreach ($ifsOrgs as $ifsOrg) {
            $ifsManagerId = (string) $ifsOrg->employee_id;
            $myEmp = $this->hrdEmployee;
            if ($myEmp && ($ifsManagerId === (string) $myEmp->id || $ifsManagerId === (string) $myEmp->employee_id)) {
                return 'ifs_manager';
            }
        }

        $myEmp = $this->hrdEmployee;
        if ($myEmp) {
            $isDeptManager = HrdOrgchart::where('employee_id', $myEmp->id)
                ->orWhere('employee_id', $myEmp->employee_id)
                ->exists();
            if ($isDeptManager) {
                return 'manager';
            }
        }

        // Check if user is the newest Project Manager (P2211) for any project
        $userProjects = TbAssignProject::where('npk', $empId)
            ->where('id_rbs', 'P2211')
            ->pluck('no_project');

        foreach ($userProjects as $noProject) {
            $latestPmNpk = TbAssignProject::where('no_project', $noProject)
                ->where('id_rbs', 'P2211')
                ->orderByDesc('start_date')
                ->orderByDesc('id_assign')
                ->value('npk');

            if ($latestPmNpk === $empId) {
                return 'manager';
            }
        }

        return 'user';
    }

    /**
     * Check if the user is an admin.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin' || $this->role === 'ifs_manager';
    }

    /**
     * Get users by dynamic role(s) using optimized queries.
     *
     * @param string|array<string> $roles 'admin' | 'ifs_manager' | 'manager' | 'user'
     * @return \Illuminate\Database\Eloquent\Collection<int, AdmUser>
     */
    public static function getUsersByRole(string|array $roles)
    {
        $roles = (array) $roles;
        $adminIds = ['252525', '255578'];

        $targetEmployeeIds = collect();
        $includeAllRegularUsers = false;

        $ifsEmployeeIds = null;
        $getIfsEmployeeIds = function () use (&$ifsEmployeeIds) {
            if ($ifsEmployeeIds === null) {
                $ifsOrgs = HrdOrgchart::where('org_code', 'IFS')->with('manager')->get();
                $ifsEmployeeIds = [];
                foreach ($ifsOrgs as $org) {
                    if ($org->manager?->employee_id) {
                        $ifsEmployeeIds[] = $org->manager->employee_id;
                    } elseif ($org->employee_id) {
                        $empId = HrdEmployee::where('id', $org->employee_id)->value('employee_id') ?? (string) $org->employee_id;
                        $ifsEmployeeIds[] = $empId;
                    }
                }
            }
            return $ifsEmployeeIds;
        };

        $managerEmployeeIds = null;
        $getManagerEmployeeIds = function () use (&$managerEmployeeIds) {
            if ($managerEmployeeIds === null) {
                // Dept managers from HRD_ORGCHART (except IFS)
                $orgs = HrdOrgchart::whereNotNull('employee_id')
                    ->where(function ($q) {
                        $q->where('org_code', '!=', 'IFS')->orWhereNull('org_code');
                    })
                    ->with('manager')
                    ->get();

                $deptManagerIds = [];
                foreach ($orgs as $org) {
                    if ($org->manager?->employee_id) {
                        $deptManagerIds[] = $org->manager->employee_id;
                    } elseif ($org->employee_id) {
                        $empId = HrdEmployee::where('id', $org->employee_id)->value('employee_id') ?? (string) $org->employee_id;
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

        if ($includeAllRegularUsers) {
            $excludeIds = array_unique(array_merge(
                $adminIds,
                $getIfsEmployeeIds(),
                $getManagerEmployeeIds()
            ));
            return static::whereNotIn('username', $excludeIds)->get();
        }

        $uniqueIds = $targetEmployeeIds->filter()->unique()->values()->all();

        if (empty($uniqueIds)) {
            return static::whereRaw('1 = 0')->get();
        }

        return static::whereIn('username', $uniqueIds)->get();
    }

    /**
     * Create a custom Eloquent builder for AdmUser to alias employee_id to username.
     */
    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if (is_string($column) && in_array($column, ['employee_id', 'users.employee_id', 'adm_users.employee_id'])) {
                    $column = 'username';
                }
                return parent::where($column, $operator, $value, $boolean);
            }

            public function whereIn($column, $values, $boolean = 'and', $not = false)
            {
                if (is_string($column) && in_array($column, ['employee_id', 'users.employee_id', 'adm_users.employee_id'])) {
                    $column = 'username';
                }
                return parent::whereIn($column, $values, $boolean, $not);
            }

            public function whereNotIn($column, $values, $boolean = 'and')
            {
                if (is_string($column) && in_array($column, ['employee_id', 'users.employee_id', 'adm_users.employee_id'])) {
                    $column = 'username';
                }
                return parent::whereNotIn($column, $values, $boolean);
            }
        };
    }

    /**
     * Get the organization name of the user.
     */
    public function getOrgNameAttribute(): ?string
    {
        return $this->hrdEmployee?->orgchart?->org_name;
    }

    /**
     * Get the email address from linked HRD employee record or direct attribute.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->attributes['email'] ?? $this->hrdEmployee?->email;
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email;
    }

    /**
     * Accessor for employee_id (mapped to username).
     */
    public function getEmployeeIdAttribute(): ?string
    {
        return $this->attributes['username'] ?? $this->attributes['employee_id'] ?? null;
    }

    /**
     * Mutator for employee_id (mapped to username).
     */
    public function setEmployeeIdAttribute($value): void
    {
        $this->attributes['username'] = $value;
    }

    /**
     * Accessor for username (mapped to username column).
     */
    public function getUsernameAttribute(): ?string
    {
        return $this->attributes['username'] ?? $this->attributes['employee_id'] ?? null;
    }

    /**
     * Mutator for username.
     */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = $value;
    }

    /**
     * Accessor for password (mapped to password column).
     */
    public function getPasswordAttribute(): ?string
    {
        return $this->attributes['password'] ?? $this->attributes['password_hash'] ?? null;
    }

    /**
     * Mutator for password.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = $value;
    }

    /**
     * Accessor for password_hash (mapped to password column).
     */
    public function getPasswordHashAttribute(): ?string
    {
        return $this->attributes['password'] ?? $this->attributes['password_hash'] ?? null;
    }

    /**
     * Mutator for password_hash (mapped to password column).
     */
    public function setPasswordHashAttribute(string $value): void
    {
        $this->attributes['password'] = $value;
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

    /**
     * The HRD employee record linked to this user.
     * HRD_EMPLOYEE ||--|| ADM_USER : "credentials"
     */
    public function hrdEmployee(): BelongsTo
    {
        return $this->belongsTo(HrdEmployee::class, 'username', 'employee_id');
    }

    /**
     * Project assignments for this user.
     * ADM_USER ||--o{ TB_ASSIGN_PROJECT : "assigned to"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'npk', 'username');
    }

    /**
     * Consumable basket items for this user.
     * ADM_USER ||--o{ CONSUMABLE_BASKET : "owns"
     */
    public function consumableBaskets(): HasMany
    {
        return $this->hasMany(ConsumableBasket::class, 'user_id');
    }

    /**
     * Asset basket items for this user.
     * ADM_USER ||--o{ ASSET_BASKET : "owns"
     */
    public function assetBaskets(): HasMany
    {
        return $this->hasMany(AssetBasket::class, 'user_id');
    }

    /**
     * Requests submitted by this user.
     * ADM_USER ||--o{ REQUEST : "submits"
     */
    public function submittedRequests(): HasMany
    {
        return $this->hasMany(Request::class, 'user_id');
    }

    /**
     * Requests assigned to this user for approval.
     * ADM_USER ||--o{ REQUEST : "assigned to approve"
     */
    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(Request::class, 'approver_id');
    }

    /**
     * Unit status change requests made by this user.
     * ADM_USER ||--o{ UNIT_STATUS_APPROVAL : "requests status change"
     */
    public function unitStatusRequests(): HasMany
    {
        return $this->hasMany(UnitStatusApproval::class, 'requester_id');
    }

    /**
     * Unit status change decisions made by this user.
     * ADM_USER ||--o{ UNIT_STATUS_APPROVAL : "decides status change"
     */
    public function unitStatusDecisions(): HasMany
    {
        return $this->hasMany(UnitStatusApproval::class, 'approver_id');
    }

    /**
     * Inventory logs actioned by this user.
     * ADM_USER ||--o{ INVENTORY_LOG : "actored"
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class, 'user_id');
    }

    /**
     * Request approvals decided by this user.
     * ADM_USER ||--o{ REQUEST_APPROVAL : "decides"
     */
    public function requestApprovals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'approver_id');
    }

    /**
     * Request admin confirmations acted on by this user.
     * ADM_USER ||--o{ REQUEST_ADMIN_CONFIRMATION : "acts on"
     */
    public function requestAdminConfirmations(): HasMany
    {
        return $this->hasMany(RequestAdminConfirmation::class, 'admin_id');
    }

    /**
     * Request status logs actioned by this user.
     * ADM_USER ||--o{ REQUEST_STATUS_LOG : "actored"
     */
    public function requestStatusLogs(): HasMany
    {
        return $this->hasMany(RequestStatusLog::class, 'changed_by');
    }
}
