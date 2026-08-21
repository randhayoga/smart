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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdmUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'adm_users';

    protected $fillable = [
        'employee_id',
        'password_hash',
        'name',
    ];

    protected $appends = [
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
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the password attribute for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Get the dynamic role of the user.
     */
    public function getRoleAttribute(): string
    {
        $admins = ['255578'];
        if (in_array($this->employee_id, $admins) || (app()->runningUnitTests() && !config('app.disable_test_admin_bypass'))) {
            return 'admin';
        }

        $ifsOrg = HrdOrgchart::where('org_code', 'IFS')->first();
        if ($ifsOrg && $ifsOrg->employee_id === $this->employee_id) {
            return 'ifs_manager';
        }

        if (HrdOrgchart::where('employee_id', $this->employee_id)->exists()) {
            return 'manager';
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
        $adminIds = ['255578'];
        $ifsEmployeeId = HrdOrgchart::where('org_code', 'IFS')->value('employee_id');
        $managerEmployeeIds = HrdOrgchart::whereNotNull('employee_id')->pluck('employee_id')->filter()->toArray();

        $targetEmployeeIds = collect();
        $includeAllRegularUsers = false;

        foreach ($roles as $role) {
            switch ($role) {
                case 'admin':
                    $targetEmployeeIds = $targetEmployeeIds->merge($adminIds);
                    break;
                case 'ifs_manager':
                    if ($ifsEmployeeId) {
                        $targetEmployeeIds->push($ifsEmployeeId);
                    }
                    break;
                case 'manager':
                    $targetEmployeeIds = $targetEmployeeIds->merge($managerEmployeeIds);
                    break;
                case 'user':
                    $includeAllRegularUsers = true;
                    break;
            }
        }

        if ($includeAllRegularUsers) {
            $excludeIds = array_unique(array_merge($adminIds, $managerEmployeeIds));
            return static::whereNotIn('employee_id', $excludeIds)->get();
        }

        $uniqueIds = $targetEmployeeIds->filter()->unique()->values()->all();

        if (empty($uniqueIds)) {
            return static::whereRaw('1 = 0')->get();
        }

        return static::whereIn('employee_id', $uniqueIds)->get();
    }

    /**
     * Get the organization name of the user.
     */
    public function getOrgNameAttribute(): ?string
    {
        return $this->hrdEmployee?->orgchart?->org_name;
    }

    /**
     * Get the email address from linked HRD employee record.
     */
    public function getEmailAttribute(): ?string
    {
        return $this->hrdEmployee?->email;
    }

    /**
     * Route notifications for the mail channel.
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email;
    }

    /**
     * Accessor for username (mapped to employee_id).
     */
    public function getUsernameAttribute(): string
    {
        return $this->employee_id;
    }

    /**
     * Mutator for username (mapped to employee_id).
     */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['employee_id'] = $value;
    }

    /**
     * Accessor for password (mapped to password_hash).
     */
    public function getPasswordAttribute()
    {
        return $this->password_hash;
    }

    /**
     * Mutator for password (mapped to password_hash).
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = $value;
    }

    /**
     * The HRD employee record linked to this user.
     * HRD_EMPLOYEE ||--|| ADM_USER : "credentials"
     */
    public function hrdEmployee(): BelongsTo
    {
        return $this->belongsTo(HrdEmployee::class, 'employee_id', 'employee_id');
    }

    /**
     * Project assignments for this user.
     * ADM_USER ||--o{ TB_ASSIGN_PROJECT : "assigned to"
     */
    public function assignProjects(): HasMany
    {
        return $this->hasMany(TbAssignProject::class, 'npk', 'employee_id');
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
