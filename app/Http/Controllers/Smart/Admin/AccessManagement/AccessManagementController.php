<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\HrdOrgchart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccessManagementController extends Controller
{
    /**
     * Display the Access Management dashboard with users, roles, and permissions.
     */
    public function index(Request $request): Response
    {
        // 1. Preload Department Managers
        $deptManagers = HrdOrgchart::query()
            ->whereNotNull('employee_id')
            ->where('employee_id', '!=', '')
            ->get(['id', 'employee_id', 'org_name']);

        $managedDeptsByUser = [];
        foreach ($deptManagers as $dm) {
            $empKey = trim((string) $dm->employee_id);
            $managedDeptsByUser[$empKey][] = $dm->org_name;
        }

        // 2. Preload Latest Active Project Managers (P2211)
        $pmRows = DB::connection('reportal')
            ->table('tb_assign_project')
            ->selectRaw('no_project, npk, ROW_NUMBER() OVER (PARTITION BY no_project ORDER BY start_date DESC, id_assign DESC) as rn')
            ->where('id_rbs', 'P2211')
            ->whereNotNull('no_project')
            ->get();

        $managedProjectsByNpk = [];
        foreach ($pmRows as $pm) {
            if ((int) $pm->rn === 1 && !empty($pm->npk)) {
                $managedProjectsByNpk[trim((string) $pm->npk)][] = trim((string) $pm->no_project);
            }
        }

        // 3. Fetch active employees with their department and assigned database roles
        $users = User::query()
            ->leftJoin('hrd_orgchart as o', 'o.id', '=', 'hrd_employee.orgchart_id')
            ->select([
                'hrd_employee.id',
                'hrd_employee.employee_id',
                'hrd_employee.employee_name',
                'o.org_name as department_name',
            ])
            ->where('hrd_employee.active', 1)
            ->with('roles:id,name')
            ->orderBy('hrd_employee.employee_name')
            ->get()
            ->map(function (User $u) use ($managedDeptsByUser, $managedProjectsByNpk) {
                $empId = trim((string) $u->employee_id);
                $empName = $u->employee_name ?? $u->name;

                $depts = array_merge(
                    $managedDeptsByUser[(string) $u->id] ?? [],
                    $managedDeptsByUser[$empId] ?? []
                );
                $projects = $managedProjectsByNpk[$empId] ?? [];

                $managerOf = array_values(array_unique(array_merge($depts, $projects)));

                return [
                    'id' => $u->id,
                    'employee_id' => $empId,
                    'employee_name' => $empName,
                    'display_name' => "{$empId} - {$empName}",
                    'department' => $u->department_name ?: '-',
                    'manager_of' => $managerOf,
                    'role' => $u->role,
                    'roles' => $u->roles->pluck('name'),
                ];
            });

        // 2. Fetch all roles with their associated permissions and accurate active user counts
        $userCountsByRole = $users->groupBy('role')->map->count();
        $roleOrder = [
            'superadmin' => 1,
            'admin_cfs' => 2,
            'admin_ict' => 3,
            'ifs_manager' => 4,
            'manager' => 5,
            'user' => 6,
        ];

        $roles = Role::with('permissions')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Role $r) => $roleOrder[$r->name] ?? (100 + $r->id))
            ->values()
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
