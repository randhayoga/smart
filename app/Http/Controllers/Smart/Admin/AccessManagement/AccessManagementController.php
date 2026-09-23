<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessManagementController extends Controller
{
    /**
     * Display the Access Management dashboard with users, roles, and permissions.
     */
    public function index(Request $request): Response
    {
        // 1. Fetch active employees with their assigned database roles
        $users = User::query()
            ->where('active', 1)
            ->with('roles')
            ->orderBy('employee_name')
            ->get()
            ->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'employee_id' => $u->employee_id,
                    'employee_name' => $u->employee_name ?? $u->name,
                    'display_name' => "{$u->employee_id} - " . ($u->employee_name ?? $u->name),
                    'role' => $u->role,
                    'roles' => $u->roles->pluck('name'),
                ];
            });

        // 2. Fetch all roles with their associated permissions and accurate active user counts
        $userCountsByRole = $users->groupBy('role')->map->count();

        $roles = Role::with('permissions')
            ->orderBy('id')
            ->get()
            ->map(function (Role $r) use ($userCountsByRole) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'label' => $r->label,
                    'description' => $r->description,
                    'permissions' => $r->permissions->pluck('name'),
                    'users_count' => $userCountsByRole->get($r->name, 0),
                ];
            });

        // 3. Fetch all granular permissions grouped by functional area
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'group']);

        return Inertia::render('Smart/Superadmin/AccessManagement', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }
}
