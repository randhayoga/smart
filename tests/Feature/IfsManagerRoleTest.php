<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HrdOrgchart;
use App\Models\HrdEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IFS Manager Role Feature Tests
 *
 * Verifies dynamic role computation (user, manager, ifs_manager) and role-based middleware authorization.
 */
class IfsManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable the unit test admin bypass so we can test actual roles
        config(['app.disable_test_admin_bypass' => true]);
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_dynamic_role_assignment(): void
    {
        // 1. Standard user (employee exists, but is not designated as manager of any orgchart)
        $userObj = User::factory()->create();
        $this->assertEquals('user', $userObj->role);
        $this->assertFalse($userObj->is_admin);

        // 2. Standard manager (designated as manager of a non-IFS orgchart)
        $managerUser = User::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);

        // Refresh model/relations
        $managerUser->refresh();
        $this->assertEquals('manager', $managerUser->role);
        $this->assertFalse($managerUser->is_admin);

        // 3. IFS Manager (designated as manager of IFS orgchart)
        $ifsManagerUser = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManagerUser->employee_id)->first();
        $ifsOrgchart = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrgchart->update([
            'employee_id' => $ifsManagerUser->employee_id,
            'org_code' => 'IFS'
        ]);

        $ifsManagerUser->refresh();
        $this->assertEquals('ifs_manager', $ifsManagerUser->role);
        $this->assertTrue($ifsManagerUser->is_admin);
    }

    public function test_ifs_manager_role_middleware_authorization(): void
    {
        // Setup a standard manager
        $managerUser = User::factory()->create();
        $employee = HrdEmployee::where('employee_id', $managerUser->employee_id)->first();
        $orgchart = HrdOrgchart::find($employee->orgchart_id);
        $orgchart->update(['employee_id' => $managerUser->employee_id]);

        // Setup an IFS manager
        $ifsManagerUser = User::factory()->create();
        $ifsEmployee = HrdEmployee::where('employee_id', $ifsManagerUser->employee_id)->first();
        $ifsOrgchart = HrdOrgchart::find($ifsEmployee->orgchart_id);
        $ifsOrgchart->update([
            'employee_id' => $ifsManagerUser->employee_id,
            'org_code' => 'IFS'
        ]);

        // Setup a standard user
        $standardUser = User::factory()->create();

        // 1. Test Admin-only routes (must be 403 Forbidden for IFS Manager now)
        $adminOnlyRoutes = [
            route('smart.inventory'),
            route('smart.master'),
            route('smart.scan-barcode'),
            route('smart.inventory.pending-nonaktif'),
            route('smart.requests.index'),
            route('smart.arsip'),
        ];

        foreach ($adminOnlyRoutes as $adminRoute) {
            $this->actingAs($standardUser)->get($adminRoute)->assertStatus(403);
            $this->actingAs($managerUser)->get($adminRoute)->assertStatus(403);
            $this->actingAs($ifsManagerUser)->get($adminRoute)->assertStatus(403);
        }

        // 2. Test Shared routes accessible by IFS Manager (200 OK)
        $sharedRoutes = [
            route('smart.dashboard'),
            route('smart.inventory.stok-habis-pakai'),
            route('smart.inventory.assets'),
            route('smart.karyawan.index'),
            route('smart.audit'),
            route('smart.audit-stok'),
            route('smart.approve-status'),
        ];

        foreach ($sharedRoutes as $sharedRoute) {
            $this->actingAs($ifsManagerUser)->get($sharedRoute)->assertStatus(200);
        }

        // 3. Test Audit Trail route returns proper component
        $response = $this->actingAs($ifsManagerUser)->get(route('smart.audit'));
        $response->assertStatus(200);
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Smart/Admin/JejakAudit')
            ->has('lifecycles')
        );
    }


    public function test_ifs_org_code_resolution_based_on_environment(): void
    {
        $this->assertEquals('IFS', User::getIfsOrgCode());

        $originalEnv = $this->app['env'];
        try {
            $this->app['env'] = 'local';
            $this->assertEquals('TEST-DEPT', User::getIfsOrgCode());
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_dynamic_role_resolves_test_dept_manager_as_ifs_manager_in_local_env(): void
    {
        // 1. Setup TEST-DEPT manager
        $testDeptManager = User::factory()->create();
        $testEmployee = HrdEmployee::where('employee_id', $testDeptManager->employee_id)->first();
        $testDeptOrg = HrdOrgchart::find($testEmployee->orgchart_id);
        $testDeptOrg->update([
            'employee_id' => $testDeptManager->employee_id,
            'org_code' => 'TEST-DEPT',
        ]);

        // 2. Setup real IFS manager
        $realIfsManager = User::factory()->create();
        $realEmployee = HrdEmployee::where('employee_id', $realIfsManager->employee_id)->first();
        $realIfsOrg = HrdOrgchart::find($realEmployee->orgchart_id);
        $realIfsOrg->update([
            'employee_id' => $realIfsManager->employee_id,
            'org_code' => 'IFS',
        ]);

        $originalEnv = $this->app['env'];
        try {
            // Under local environment
            $this->app['env'] = 'local';
            $testDeptManager->refresh();
            $realIfsManager->refresh();

            $this->assertEquals('ifs_manager', $testDeptManager->role);
            $this->assertTrue($testDeptManager->is_admin);
            $this->assertEquals('manager', $realIfsManager->role);

            $ifsUsers = User::getUsersByRole('ifs_manager');
            $this->assertTrue($ifsUsers->contains('employee_id', $testDeptManager->employee_id));
            $this->assertFalse($ifsUsers->contains('employee_id', $realIfsManager->employee_id));

            // Under testing / non-local environment
            $this->app['env'] = 'testing';
            $testDeptManager->refresh();
            $realIfsManager->refresh();

            $this->assertEquals('manager', $testDeptManager->role);
            $this->assertFalse($testDeptManager->is_admin);
            $this->assertEquals('ifs_manager', $realIfsManager->role);

            $ifsUsersNonLocal = User::getUsersByRole('ifs_manager');
            $this->assertFalse($ifsUsersNonLocal->contains('employee_id', $testDeptManager->employee_id));
            $this->assertTrue($ifsUsersNonLocal->contains('employee_id', $realIfsManager->employee_id));
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}

