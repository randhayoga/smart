<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'label',
        'description',
    ];

    public function __construct(array $attributes = [])
    {
        $this->connection = config('database.default', 'SMART');
        parent::__construct($attributes);
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    /**
     * Query builder for users that hold this role (cross-database between SMART and USER_HRIS).
     */
    public function users()
    {
        $userIds = DB::connection($this->getConnectionName() ?? 'SMART')
            ->table('user_roles')
            ->where('role_id', $this->id)
            ->pluck('user_id');

        return User::whereIn('id', $userIds);
    }

    /**
     * Accessor for users collection.
     */
    public function getUsersAttribute()
    {
        return $this->users()->get();
    }

    /**
     * Grant one or more permissions to the role.
     */
    public function givePermissionTo(string|Permission ...$permissions): self
    {
        foreach ($permissions as $permission) {
            $permissionModel = is_string($permission)
                ? Permission::firstOrCreate(['name' => $permission], ['label' => ucfirst($permission), 'group' => 'general'])
                : $permission;

            $this->permissions()->syncWithoutDetaching([$permissionModel->id]);
        }

        return $this;
    }

    /**
     * Revoke one or more permissions from the role.
     */
    public function revokePermissionTo(string|Permission ...$permissions): self
    {
        foreach ($permissions as $permission) {
            $permissionModel = is_string($permission)
                ? Permission::where('name', $permission)->first()
                : $permission;

            if ($permissionModel) {
                $this->permissions()->detach($permissionModel->id);
            }
        }

        return $this;
    }

    /**
     * Determine if the role possesses a specific permission.
     */
    public function hasPermissionTo(string|Permission $permission): bool
    {
        $permissionName = is_string($permission) ? $permission : $permission->name;

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('name', $permissionName);
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }
}
