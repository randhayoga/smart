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

        // 1. Test Admin-only route (smart.inventory / smart.dashboard)
        // Standard User -> Forbidden
        $response = $this->actingAs($standardUser)->get(route('smart.inventory'));
        $response->assertStatus(403);

        // Standard Manager -> Forbidden
        $response = $this->actingAs($managerUser)->get(route('smart.inventory'));
        $response->assertStatus(403);

        // IFS Manager -> Allowed (since they have admin privileges, it returns Inertia render 200)
        $response = $this->actingAs($ifsManagerUser)->get(route('smart.inventory'));
        $response->assertStatus(200);

        // 2. Test Manager-only route (smart.approve-status)
        // Standard User -> Forbidden
        $response = $this->actingAs($standardUser)->get(route('smart.approve-status'));
        $response->assertStatus(403);

        // IFS Manager -> Allowed (since ifs_manager satisfies manager role in hierarchy)
        $response = $this->actingAs($ifsManagerUser)->get(route('smart.approve-status'));
        $response->assertStatus(200);
    }
}
