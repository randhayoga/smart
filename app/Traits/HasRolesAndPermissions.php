<?php

namespace App\Traits;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    /**
     * The roles assigned to this user in the SMART database.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * Determine if the user holds a specific role or any of the given roles.
     *
     * @param string|array<string>|Role $roles
     */
    public function hasRole(string|array|Role $roles): bool
    {
        if ($roles instanceof Role) {
            $roles = [$roles->name];
        } else {
            $roles = (array) $roles;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn(Role $r) => in_array($r->name, $roles, true));
        }

        return $this->roles()->whereIn('name', $roles)->exists();
    }

    /**
     * Determine if the user has a specific permission via any assigned role.
     */
    public function hasPermission(string|Permission $permission): bool
    {
        $permissionName = is_string($permission) ? $permission : $permission->name;

        if ($this->relationLoaded('roles')) {
            foreach ($this->roles as $role) {
                if ($role->hasPermissionTo($permissionName)) {
                    return true;
                }
            }
            return false;
        }

        return $this->roles()
            ->whereHas('permissions', fn($q) => $q->where('name', $permissionName))
            ->exists();
    }

    /**
     * Assign one or more roles to the user.
     */
    public function assignRole(string|Role ...$roles): self
    {
        $this->cachedRoleName = null;

        foreach ($roles as $role) {
            $roleModel = is_string($role)
                ? Role::where('name', $role)->firstOrFail()
                : $role;

            $this->roles()->syncWithoutDetaching([$roleModel->id]);
        }

        return $this;
    }

    /**
     * Remove one or more roles from the user.
     */
    public function removeRole(string|Role ...$roles): self
    {
        $this->cachedRoleName = null;

        foreach ($roles as $role) {
            $roleModel = is_string($role)
                ? Role::where('name', $role)->first()
                : $role;

            if ($roleModel) {
                $this->roles()->detach($roleModel->id);
            }
        }

        return $this;
    }

    /**
     * Synchronize the user's assigned roles.
     *
     * @param array<string|Role> $roles
     */
    public function syncRoles(array $roles): self
    {
        $this->cachedRoleName = null;

        $roleIds = [];
        foreach ($roles as $role) {
            $roleModel = is_string($role)
                ? Role::where('name', $role)->firstOrFail()
                : $role;
            $roleIds[] = $roleModel->id;
        }

        $this->roles()->sync($roleIds);

        return $this;
    }

    /**
     * Get all distinct permissions granted to this user across all roles.
     */
    public function getAllPermissions(): Collection
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->flatMap(fn(Role $r) => $r->permissions)->unique('id')->values();
        }

        return Permission::whereHas('roles', function ($q) {
            $q->whereIn('roles.id', $this->roles()->pluck('roles.id'));
        })->get();
    }
}
