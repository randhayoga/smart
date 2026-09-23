<?php

namespace Tests\Feature\Admin;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\TbRbs;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
        $this->seed(RoleAndPermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_access_access_management(): void
    {
        $response = $this->get(route('smart.access.index'));
        $response->assertRedirectContains(route('login'));
    }

    public function test_non_superadmin_user_cannot_access_access_management(): void
    {
        $admin = User::factory()->create(['employee_id' => '255578']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('smart.access.index'));
        $response->assertStatus(403);

        $regularUser = User::factory()->create(['employee_id' => '123456']);
        $response = $this->actingAs($regularUser)->get(route('smart.access.index'));
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_access_management_page(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);

        $response = $this->actingAs($superadmin)->get(route('smart.access.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn(Assert $page) => $page
            ->component('Smart/Superadmin/AccessManagement')
            ->has('users')
            ->has('roles')
            ->has('permissions')
        );
    }

    public function test_superadmin_can_update_user_role(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $targetUser = User::factory()->create();

        $this->assertEquals('user', $targetUser->role);

        $response = $this->actingAs($superadmin)->put(route('smart.access.users.role.update', $targetUser->id), [
            'role' => 'admin',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $targetUser->refresh();
        $this->assertTrue($targetUser->hasRole('admin'));
        $this->assertEquals('admin', $targetUser->role);
    }

    public function test_superadmin_cannot_assign_manager_or_ifs_manager_manually(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $targetUser = User::factory()->create();

        $response1 = $this->actingAs($superadmin)->put(route('smart.access.users.role.update', $targetUser->id), [
            'role' => 'manager',
        ]);
        $response1->assertSessionHasErrors('role');

        $response2 = $this->actingAs($superadmin)->put(route('smart.access.users.role.update', $targetUser->id), [
            'role' => 'ifs_manager',
        ]);
        $response2->assertSessionHasErrors('role');
    }

    public function test_superadmin_can_create_custom_role(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);

        $response = $this->actingAs($superadmin)->post(route('smart.access.roles.store'), [
            'name' => 'warehouse_lead',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'warehouse_lead',
        ], 'SMART');
    }

    public function test_superadmin_can_update_role_name(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $role = Role::create(['name' => 'temp_role', 'label' => 'Temp Role']);

        $response = $this->actingAs($superadmin)->put(route('smart.access.roles.update', $role->id), [
            'name' => 'renamed_role',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'renamed_role',
        ], 'SMART');
    }

    public function test_superadmin_cannot_delete_system_protected_role(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $response = $this->actingAs($superadmin)->delete(route('smart.access.roles.destroy', $adminRole->id));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id], 'SMART');
    }

    public function test_superadmin_cannot_delete_role_with_assigned_users(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $role = Role::create(['name' => 'staff_role', 'label' => 'Staff Role']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($superadmin)->delete(route('smart.access.roles.destroy', $role->id));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id], 'SMART');
    }

    public function test_superadmin_can_delete_unassigned_custom_role(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $role = Role::create(['name' => 'obsolete_role', 'label' => 'Obsolete Role']);

        $response = $this->actingAs($superadmin)->delete(route('smart.access.roles.destroy', $role->id));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id], 'SMART');
    }

    public function test_superadmin_can_toggle_role_permission(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);
        $role = Role::create(['name' => 'auditor', 'label' => 'Auditor']);

        $this->assertFalse($role->hasPermissionTo('audit.view'));

        // Grant permission using 'enabled' (bug fix verification)
        $response = $this->actingAs($superadmin)->put(route('smart.access.roles.permissions.update', $role->id), [
            'permission' => 'audit.view',
            'enabled' => true,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertTrue($role->fresh()->hasPermissionTo('audit.view'));

        // Revoke permission using 'granted' => false
        $response = $this->actingAs($superadmin)->put(route('smart.access.roles.permissions.update', $role->id), [
            'permission' => 'audit.view',
            'granted' => false,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertFalse($role->fresh()->hasPermissionTo('audit.view'));
    }

    public function test_superadmin_can_sync_managers_and_regular_users_from_hris(): void
    {
        $superadmin = User::factory()->create(['employee_id' => '265656']);

        // 1. Dept Manager
        $deptManager = User::factory()->create();
        $deptOrg = HrdOrgchart::find($deptManager->orgchart_id);
        $deptOrg->update([
            'employee_id' => $deptManager->id,
            'org_code' => 'OPS',
        ]);

        // 2. Project Manager
        $pmRbs = TbRbs::firstOrCreate(['id' => 'P2211'], ['name' => 'Project Manager', 'showing_name' => 'Project Manager']);
        $proj = TbProject::factory()->create(['no_project' => 'PRJ-SYNC-1']);
        $pmUser = User::factory()->create();
        TbAssignProject::create([
            'npk' => $pmUser->employee_id,
            'no_project' => $proj->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => now()->subMonth(),
        ]);

        // 3. IFS Manager
        $ifsUser = User::factory()->create();
        $ifsOrg = HrdOrgchart::find($ifsUser->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsUser->id,
            'org_code' => 'IFS',
        ]);

        // 4. Regular Employee (should be synced to 'user')
        $regularUser = User::factory()->create(['active' => 1]);

        $response = $this->actingAs($superadmin)->post(route('smart.access.sync-managers'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('access.sync_success'));

        $this->assertTrue($deptManager->fresh()->hasRole('manager'));
        $this->assertTrue($pmUser->fresh()->hasRole('manager'));
        $this->assertTrue($ifsUser->fresh()->hasRole('ifs_manager'));
        $this->assertTrue($regularUser->fresh()->hasRole('user'));
    }

    public function test_admin_role_does_not_have_access_manage_permission(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $superadminRole = Role::where('name', 'superadmin')->first();

        $this->assertNotNull($adminRole);
        $this->assertNotNull($superadminRole);

        $this->assertTrue($superadminRole->hasPermissionTo('access.manage'));
        $this->assertFalse($adminRole->hasPermissionTo('access.manage'));
    }
}
