<?php

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_migration_creates_expected_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('role_permissions'));
        $this->assertTrue(Schema::hasTable('user_roles'));

        $this->assertTrue(Schema::hasColumns('roles', ['id', 'name', 'label', 'description', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('permissions', ['id', 'name', 'label', 'group', 'description', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('role_permissions', ['id', 'role_id', 'permission_id', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('user_roles', ['id', 'user_id', 'role_id', 'created_at', 'updated_at']));
    }

    public function test_seeder_populates_roles_and_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $expectedRoles = ['superadmin', 'admin', 'ifs_manager', 'manager', 'user'];
        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName], 'SMART');
        }

        // Verify key permission groups exist
        $groups = ['dashboard', 'master', 'inventory', 'requests', 'karyawan', 'audit', 'notifications'];
        foreach ($groups as $group) {
            $this->assertTrue(Permission::where('group', $group)->exists());
        }

        // Verify Superadmin has all permissions, Admin has all permissions except access.manage
        $allPermissionsCount = Permission::count();
        $superadmin = Role::where('name', 'superadmin')->firstOrFail();
        $admin = Role::where('name', 'admin')->firstOrFail();

        $this->assertEquals($allPermissionsCount, $superadmin->permissions()->count());
        $this->assertEquals($allPermissionsCount - 1, $admin->permissions()->count());
        $this->assertTrue($superadmin->hasPermissionTo('access.manage'));
        $this->assertFalse($admin->hasPermissionTo('access.manage'));

        // Verify Manager has approval permissions but not master manage
        $manager = Role::where('name', 'manager')->firstOrFail();
        $this->assertTrue($manager->hasPermissionTo('requests.approve'));
        $this->assertFalse($manager->hasPermissionTo('master.manage'));

        // Verify Regular User has browse/create permissions
        $user = Role::where('name', 'user')->firstOrFail();
        $this->assertTrue($user->hasPermissionTo('requests.create'));
        $this->assertFalse($user->hasPermissionTo('requests.approve'));
    }

    public function test_seeder_assigns_hardcoded_admins_to_superadmin_and_admin_roles(): void
    {
        $superadminUser = User::factory()->create(['employee_id' => '265656']);
        $adminUser = User::factory()->create(['employee_id' => '255578']);
        $regularUser = User::factory()->create(['employee_id' => '999997']);

        $this->seed(RoleAndPermissionSeeder::class);

        $superadminUser->refresh();
        $adminUser->refresh();
        $regularUser->refresh();

        $this->assertTrue($superadminUser->hasRole('superadmin'));
        $this->assertTrue($adminUser->hasRole('admin'));
        $this->assertFalse($regularUser->hasRole('admin'));
    }

    public function test_role_model_relationships_and_permission_helpers(): void
    {
        $role = Role::create([
            'name' => 'custom_role',
            'label' => 'Custom Role',
        ]);

        $perm1 = Permission::create([
            'name' => 'test.perm1',
            'label' => 'Test Permission 1',
            'group' => 'test',
        ]);

        $perm2 = Permission::create([
            'name' => 'test.perm2',
            'label' => 'Test Permission 2',
            'group' => 'test',
        ]);

        $this->assertFalse($role->hasPermissionTo('test.perm1'));

        $role->givePermissionTo('test.perm1', $perm2);
        $this->assertTrue($role->hasPermissionTo('test.perm1'));
        $this->assertTrue($role->hasPermissionTo('test.perm2'));

        $role->revokePermissionTo('test.perm1');
        $this->assertFalse($role->hasPermissionTo('test.perm1'));
        $this->assertTrue($role->hasPermissionTo('test.perm2'));
    }

    public function test_user_has_roles_and_permissions_trait(): void
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'inventory_officer',
            'label' => 'Inventory Officer',
        ]);

        $perm = Permission::create([
            'name' => 'inventory.inspect',
            'label' => 'Inspect Inventory',
            'group' => 'inventory',
        ]);

        $role->givePermissionTo($perm);

        $this->assertFalse($user->hasRole('inventory_officer'));
        $this->assertFalse($user->hasPermission('inventory.inspect'));

        $user->assignRole('inventory_officer');
        $this->assertTrue($user->hasRole('inventory_officer'));
        $this->assertTrue($user->hasPermission('inventory.inspect'));

        $user->removeRole('inventory_officer');
        $this->assertFalse($user->hasRole('inventory_officer'));
        $this->assertFalse($user->hasPermission('inventory.inspect'));
    }

    public function test_user_role_attribute_prefers_database_role_with_fallback(): void
    {
        $user = User::factory()->create();

        $adminRole = Role::create([
            'name' => 'admin',
            'label' => 'Administrator',
        ]);

        // Prior to DB assignment, regular user role falls back to legacy logic ('user')
        $this->assertEquals('user', $user->role);

        // When DB role is assigned, $user->role reflects it
        $user->assignRole($adminRole);
        $user->refresh();

        $this->assertEquals('admin', $user->role);
        $this->assertTrue($user->is_admin);
    }
}
