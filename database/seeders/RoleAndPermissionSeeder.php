<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Roles definition
        $rolesData = [
            'superadmin' => [
                'label' => 'Super Administrator',
                'description' => 'System super administrator with full unrestricted access.',
            ],
            'admin' => [
                'label' => 'Administrator',
                'description' => 'Administrative user with full inventory, request, and master data management privileges.',
            ],
            'ifs_manager' => [
                'label' => 'IFS Manager',
                'description' => 'IFS Department Manager handling inventory audits, asset status approvals, and department approvals.',
            ],
            'manager' => [
                'label' => 'Department / Project Manager',
                'description' => 'Managers approving requisitions and managing project allocations.',
            ],
            'user' => [
                'label' => 'Employee',
                'description' => 'Standard employee with catalog browsing, cart management, and request tracking capabilities.',
            ],
        ];

        $roles = [];
        foreach ($rolesData as $name => $data) {
            $roles[$name] = Role::firstOrCreate(
                ['name' => $name],
                [
                    'label' => $data['label'],
                    'description' => $data['description'],
                ]
            );
        }

        // 2. Granular Permissions definition
        $permissionsData = [
            // Dashboard
            ['name' => 'dashboard.admin.view', 'label' => 'View Admin Dashboard', 'group' => 'dashboard'],
            ['name' => 'dashboard.user.view', 'label' => 'View User Dashboard', 'group' => 'dashboard'],

            // Master Data
            ['name' => 'master.view', 'label' => 'View Master Data', 'group' => 'master'],
            ['name' => 'master.manage', 'label' => 'Manage Master Data (Categories, Locations, etc.)', 'group' => 'master'],

            // Inventory
            ['name' => 'inventory.view', 'label' => 'View Inventory Catalog, Lots & Assets', 'group' => 'inventory'],
            ['name' => 'inventory.manage', 'label' => 'Manage Inventory Items, Lots, and Units', 'group' => 'inventory'],
            ['name' => 'inventory.borrow', 'label' => 'Perform Direct Unit Borrow & Return', 'group' => 'inventory'],
            ['name' => 'inventory.manual_request', 'label' => 'Create Manual Stock Requests', 'group' => 'inventory'],
            ['name' => 'inventory.status_approval.request', 'label' => 'Submit Unit Status Change Requests', 'group' => 'inventory'],
            ['name' => 'inventory.status_approval.decide', 'label' => 'Approve or Reject Unit Status Changes', 'group' => 'inventory'],

            // Requests / Requisitions
            ['name' => 'requests.create', 'label' => 'Create and Submit Requisitions (Cart)', 'group' => 'requests'],
            ['name' => 'requests.view_own', 'label' => 'View Own Requisition History & Cancel', 'group' => 'requests'],
            ['name' => 'requests.approve', 'label' => 'Approve or Reject Subordinate Requisitions', 'group' => 'requests'],
            ['name' => 'requests.inbox.view', 'label' => 'View Approved Requests Inbox', 'group' => 'requests'],
            ['name' => 'requests.confirm', 'label' => 'Confirm and Review Requisitions', 'group' => 'requests'],
            ['name' => 'requests.fulfill', 'label' => 'Allocate Units/Lots and Confirm Fulfillment', 'group' => 'requests'],
            ['name' => 'requests.handover', 'label' => 'Schedule and Process Item Handovers', 'group' => 'requests'],
            ['name' => 'requests.returns', 'label' => 'Process and Confirm Item Returns', 'group' => 'requests'],
            ['name' => 'requests.archive.view', 'label' => 'View Requisition Archive', 'group' => 'requests'],

            // Directory / Karyawan
            ['name' => 'karyawan.view', 'label' => 'View Employee Directory and Active Loans', 'group' => 'karyawan'],

            // Audit
            ['name' => 'audit.view', 'label' => 'View Inventory Activity Logs and Stock Audits', 'group' => 'audit'],

            // Notifications
            ['name' => 'notifications.manage', 'label' => 'View and Manage Personal Notifications', 'group' => 'notifications'],

            // Access Management (Exclusive to Superadmin)
            ['name' => 'access.manage', 'label' => 'Manage System Roles and Permissions', 'group' => 'access'],
        ];

        // Cleanup old granular access permissions if previously seeded
        $obsoleteAccessPermissions = ['access.view', 'access.users.manage', 'access.roles.manage', 'access.permissions.manage', 'access.sync'];
        $obsoleteIds = Permission::whereIn('name', $obsoleteAccessPermissions)->pluck('id');
        if ($obsoleteIds->isNotEmpty()) {
            \Illuminate\Support\Facades\DB::connection('SMART')->table('role_permissions')->whereIn('permission_id', $obsoleteIds)->delete();
            Permission::whereIn('id', $obsoleteIds)->delete();
        }

        $permissions = [];
        foreach ($permissionsData as $pData) {
            $permissions[$pData['name']] = Permission::firstOrCreate(
                ['name' => $pData['name']],
                [
                    'label' => $pData['label'],
                    'group' => $pData['group'],
                ]
            );
        }

        // 3. Map permissions to roles
        $isProduction = app()->isProduction();
        $isAlreadySeeded = $this->isAlreadySeeded();
        $shouldSyncPermissions = !$isProduction || !$isAlreadySeeded || $this->shouldForceSync();

        if ($shouldSyncPermissions) {
            $allPermissionIds = collect($permissions)->pluck('id')->all();

            // Superadmin gets all permissions (including access.manage)
            $roles['superadmin']->permissions()->sync($allPermissionIds);

            // Admin gets all permissions EXCEPT access management (exclusive to superadmin)
            $adminPermissionIds = collect($permissions)
                ->filter(fn($p) => $p->group !== 'access')
                ->pluck('id')
                ->all();
            $roles['admin']->permissions()->sync($adminPermissionIds);

            // IFS Manager permissions
            $ifsManagerPermissions = [
                'dashboard.admin.view',
                'dashboard.user.view',
                'inventory.view',
                'inventory.status_approval.decide',
                'requests.create',
                'requests.view_own',
                'requests.approve',
                'karyawan.view',
                'audit.view',
                'notifications.manage',
            ];
            $roles['ifs_manager']->permissions()->sync(
                collect($ifsManagerPermissions)->map(fn($name) => $permissions[$name]->id)->all()
            );

            // Manager permissions
            $managerPermissions = [
                'dashboard.user.view',
                'requests.create',
                'requests.view_own',
                'requests.approve',
                'inventory.status_approval.decide',
                'notifications.manage',
            ];
            $roles['manager']->permissions()->sync(
                collect($managerPermissions)->map(fn($name) => $permissions[$name]->id)->all()
            );

            // Regular User permissions
            $userPermissions = [
                'dashboard.user.view',
                'requests.create',
                'requests.view_own',
                'notifications.manage',
            ];
            $roles['user']->permissions()->sync(
                collect($userPermissions)->map(fn($name) => $permissions[$name]->id)->all()
            );

            if ($isProduction && $isAlreadySeeded) {
                $this->command?->warn('FORCE_ROLE_SEED detected: Reset all role permissions to factory defaults in production.');
            }
        } else {
            $this->command?->warn('RoleAndPermissionSeeder: Default permission sync skipped to protect active permissions. (Use FORCE_ROLE_SEED=true to override).');
        }

        // 4. Initial User Assignment for existing hardcoded superadmin & admins
        $superadminUser = User::where('employee_id', '265656')->first();
        if ($superadminUser && !$superadminUser->hasRole('superadmin')) {
            $superadminUser->assignRole('superadmin');
        }

        $adminUser = User::where('employee_id', '255578')->first();
        if ($adminUser && !$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
    }

    /**
     * Determine if roles and permissions have already been seeded in the database.
     */
    protected function isAlreadySeeded(): bool
    {
        return Role::where('name', 'superadmin')->exists()
            && \Illuminate\Support\Facades\DB::connection('SMART')->table('role_permissions')->exists();
    }

    /**
     * Determine if an intentional force override has been requested for production reseeding.
     */
    protected function shouldForceSync(): bool
    {
        if (filter_var(env('FORCE_ROLE_SEED', false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        if ($this->command && !app()->runningUnitTests()) {
            try {
                return $this->command->confirm(
                    'Roles and permissions already exist in production. Reseeding will overwrite all custom permissions. Do you wish to continue?',
                    false
                );
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }
}
