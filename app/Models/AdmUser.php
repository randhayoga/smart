<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'role',
        'is_admin',
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
        if (in_array($this->employee_id, $admins) || app()->runningUnitTests()) {
            return 'admin';
        }

        $ifsOrg = HrdOrgchart::where('org_code', 'IFS')->first();
        if ($ifsOrg && $ifsOrg->employee_id === $this->employee_id) {
            return 'manager';
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
        return $this->role === 'admin';
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
    public function setUsernameAttribute($value): void
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
    public function setPasswordAttribute($value): void
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
        return $this->hasMany(\App\Models\Cart\ConsumableBasket::class, 'user_id');
    }

    /**
     * Asset basket items for this user.
     * ADM_USER ||--o{ ASSET_BASKET : "owns"
     */
    public function assetBaskets(): HasMany
    {
        return $this->hasMany(\App\Models\Cart\AssetBasket::class, 'user_id');
    }

    /**
     * Requests submitted by this user.
     * ADM_USER ||--o{ REQUEST : "submits"
     */
    public function submittedRequests(): HasMany
    {
        return $this->hasMany(\App\Models\Request\Request::class, 'user_id');
    }

    /**
     * Requests assigned to this user for approval.
     * ADM_USER ||--o{ REQUEST : "assigned to approve"
     */
    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(\App\Models\Request\Request::class, 'approver_id');
    }

    /**
     * Unit status change requests made by this user.
     * ADM_USER ||--o{ UNIT_STATUS_APPROVAL : "requests status change"
     */
    public function unitStatusRequests(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\UnitStatusApproval::class, 'requester_id');
    }

    /**
     * Unit status change decisions made by this user.
     * ADM_USER ||--o{ UNIT_STATUS_APPROVAL : "decides status change"
     */
    public function unitStatusDecisions(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\UnitStatusApproval::class, 'approver_id');
    }

    /**
     * Inventory logs actioned by this user.
     * ADM_USER ||--o{ INVENTORY_LOG : "actored"
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Inventory\InventoryLog::class, 'user_id');
    }

    /**
     * Request approvals decided by this user.
     * ADM_USER ||--o{ REQUEST_APPROVAL : "decides"
     */
    public function requestApprovals(): HasMany
    {
        return $this->hasMany(\App\Models\Request\RequestApproval::class, 'approver_id');
    }

    /**
     * Request admin confirmations acted on by this user.
     * ADM_USER ||--o{ REQUEST_ADMIN_CONFIRMATION : "acts on"
     */
    public function requestAdminConfirmations(): HasMany
    {
        return $this->hasMany(\App\Models\Request\RequestAdminConfirmation::class, 'admin_id');
    }

    /**
     * Request status logs actioned by this user.
     * ADM_USER ||--o{ REQUEST_STATUS_LOG : "actored"
     */
    public function requestStatusLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Request\RequestStatusLog::class, 'changed_by');
    }
}
