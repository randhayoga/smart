<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncUserHrisManagersController extends Controller
{
    /**
     * Synchronize manager, IFS manager, and regular user roles based on USER_HRIS and RE_PORTALDB.
     * All non-managerial active employees (excluding superadmin and admin) are set to 'user'.
     */
    public function store(Request $request): RedirectResponse
    {
        $ifsOrgCode = User::getIfsOrgCode();

        // 1. Fetch Role IDs
        $roles = Role::pluck('id', 'name');
        $ifsRoleId = $roles['ifs_manager'] ?? null;
        $managerRoleId = $roles['manager'] ?? null;
        $userRoleId = $roles['user'] ?? null;
        $adminRoleId = $roles['admin'] ?? null;
        $superadminRoleId = $roles['superadmin'] ?? null;

        // 2. Identify Superadmin and Admin users to preserve their privileges
        $protectedRoles = array_filter([$superadminRoleId, $adminRoleId]);
        $existingAdminAndSuperadminIds = DB::connection('SMART')->table('user_roles')
            ->whereIn('role_id', $protectedRoles)
            ->pluck('user_id')
            ->all();

        $hardcodedSuperadminIds = User::whereIn('employee_id', ['265656'])
            ->pluck('id')
            ->all();

        $protectedUserIds = array_unique(array_merge($existingAdminAndSuperadminIds, $hardcodedSuperadminIds));

        // 3. Collect active IFS Managers
        $ifsUserIds = [];
        $ifsOrgs = HrdOrgchart::where('org_code', $ifsOrgCode)->with('manager')->get();
        foreach ($ifsOrgs as $org) {
            if ($org->manager?->id) {
                $ifsUserIds[] = $org->manager->id;
            } elseif ($org->employee_id) {
                $uid = User::where('id', $org->employee_id)
                    ->orWhere('employee_id', (string) $org->employee_id)
                    ->value('id');
                if ($uid) {
                    $ifsUserIds[] = $uid;
                }
            }
        }
        $ifsUserIds = array_diff(array_unique($ifsUserIds), $protectedUserIds);

        // 4. Collect active Department Managers
        $deptUserIds = [];
        $deptOrgs = HrdOrgchart::whereNotNull('employee_id')
            ->where(function ($q) use ($ifsOrgCode) {
                $q->whereNotIn('org_code', array_unique(['IFS', $ifsOrgCode]))
                    ->orWhereNull('org_code');
            })
            ->with('manager')
            ->get();
        foreach ($deptOrgs as $org) {
            if ($org->manager?->id) {
                $deptUserIds[] = $org->manager->id;
            } elseif ($org->employee_id) {
                $uid = User::where('id', $org->employee_id)
                    ->orWhere('employee_id', (string) $org->employee_id)
                    ->value('id');
                if ($uid) {
                    $deptUserIds[] = $uid;
                }
            }
        }

        // 5. Collect active Project Managers (P2211)
        $pmUserIds = [];
        $projectManagerNpks = TbAssignProject::where('id_rbs', 'P2211')
            ->orderByDesc('start_date')
            ->orderByDesc('id_assign')
            ->get(['no_project', 'npk'])
            ->unique('no_project')
            ->pluck('npk')
            ->filter()
            ->toArray();
        if (!empty($projectManagerNpks)) {
            $pmUserIds = User::whereIn('employee_id', $projectManagerNpks)->pluck('id')->all();
        }

        $allManagerUserIds = array_diff(array_unique(array_merge($deptUserIds, $pmUserIds)), $protectedUserIds, $ifsUserIds);

        // 6. Collect all other active users
        $allActiveUserIds = User::where('active', 1)->pluck('id')->all();
        $regularUserIds = array_diff($allActiveUserIds, $protectedUserIds, $ifsUserIds, $allManagerUserIds);

        // 7. Atomic DB sync on SMART.user_roles
        $nonProtectedUserIds = array_merge($ifsUserIds, $allManagerUserIds, $regularUserIds);
        $now = now();

        DB::connection('SMART')->transaction(function () use (
            $nonProtectedUserIds,
            $ifsUserIds,
            $ifsRoleId,
            $allManagerUserIds,
            $managerRoleId,
            $regularUserIds,
            $userRoleId,
            $now
        ) {
            // Delete existing role assignments for non-protected active users
            if (!empty($nonProtectedUserIds)) {
                foreach (array_chunk($nonProtectedUserIds, 500) as $chunk) {
                    DB::connection('SMART')->table('user_roles')
                        ->whereIn('user_id', $chunk)
                        ->delete();
                }
            }

            // Insert IFS managers
            if ($ifsRoleId && !empty($ifsUserIds)) {
                $ifsRows = array_map(fn($uid) => [
                    'user_id' => $uid,
                    'role_id' => $ifsRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $ifsUserIds);
                foreach (array_chunk($ifsRows, 200) as $chunk) {
                    DB::connection('SMART')->table('user_roles')->insert($chunk);
                }
            }

            // Insert Dept & Project managers
            if ($managerRoleId && !empty($allManagerUserIds)) {
                $mgrRows = array_map(fn($uid) => [
                    'user_id' => $uid,
                    'role_id' => $managerRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $allManagerUserIds);
                foreach (array_chunk($mgrRows, 200) as $chunk) {
                    DB::connection('SMART')->table('user_roles')->insert($chunk);
                }
            }

            // Insert Regular users
            if ($userRoleId && !empty($regularUserIds)) {
                $userRows = array_map(fn($uid) => [
                    'user_id' => $uid,
                    'role_id' => $userRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $regularUserIds);
                foreach (array_chunk($userRows, 200) as $chunk) {
                    DB::connection('SMART')->table('user_roles')->insert($chunk);
                }
            }
        });

        return back()->with('success', __('access.sync_success'));
    }
}
