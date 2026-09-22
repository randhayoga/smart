<?php

namespace Tests\Feature;

use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\TbRbs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Role and getUsersByRole Feature Tests
 *
 * Verifies dynamic role calculation and role-based user queries for Admin, IFS Manager,
 * Department Managers, and Project Managers (P2211).
 */
class UserRoleTest extends TestCase
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

    public function test_get_users_by_role_admin(): void
    {
        $admin = User::factory()->create(['employee_id' => '999998']);
        $otherUser = User::factory()->create();

        $admins = User::getUsersByRole('admin');

        $this->assertTrue($admins->contains('id', $admin->id));
        $this->assertFalse($admins->contains('id', $otherUser->id));
    }

    public function test_get_users_by_role_ifs_manager(): void
    {
        $ifsUser = User::factory()->create();
        $ifsOrg = HrdOrgchart::find($ifsUser->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsUser->id,
            'org_code' => 'IFS',
        ]);

        $otherUser = User::factory()->create();

        $ifsManagers = User::getUsersByRole('ifs_manager');

        $this->assertTrue($ifsManagers->contains('id', $ifsUser->id));
        $this->assertFalse($ifsManagers->contains('id', $otherUser->id));
    }

    public function test_get_users_by_role_manager_includes_dept_and_newest_project_managers(): void
    {
        // 1. Dept Manager
        $deptManagerUser = User::factory()->create();
        $deptOrg = HrdOrgchart::find($deptManagerUser->orgchart_id);
        $deptOrg->update([
            'employee_id' => $deptManagerUser->id,
            'org_code' => 'OPS',
        ]);

        // 2. IFS Manager (should be excluded from general manager role)
        $ifsUser = User::factory()->create();
        $ifsOrg = HrdOrgchart::find($ifsUser->orgchart_id);
        $ifsOrg->update([
            'employee_id' => $ifsUser->id,
            'org_code' => 'IFS',
        ]);

        // Setup RBS roles
        $pmRbs = TbRbs::firstOrCreate(['id' => 'P2211'], ['name' => 'Project Manager', 'showing_name' => 'Project Manager']);
        $memberRbs = TbRbs::firstOrCreate(['id' => 'P0'], ['name' => 'Anggota', 'showing_name' => 'Anggota']);

        // 3. Project 1 with Old PM and New PM
        $proj1 = TbProject::factory()->create(['no_project' => 'PRJ-101']);
        $oldPmUser = User::factory()->create();
        $newPmUser = User::factory()->create();

        TbAssignProject::create([
            'npk' => $oldPmUser->employee_id,
            'no_project' => $proj1->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2025-01-01 00:00:00',
        ]);
        TbAssignProject::create([
            'npk' => $newPmUser->employee_id,
            'no_project' => $proj1->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        // 4. Project 2 with PM and non-PM member
        $proj2 = TbProject::factory()->create(['no_project' => 'PRJ-102']);
        $pm2User = User::factory()->create();
        $memberUser = User::factory()->create();

        TbAssignProject::create([
            'npk' => $pm2User->employee_id,
            'no_project' => $proj2->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);
        TbAssignProject::create([
            'npk' => $memberUser->employee_id,
            'no_project' => $proj2->no_project,
            'id_rbs' => $memberRbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $managers = User::getUsersByRole('manager');

        // Dept Manager and newest PMs should be included
        $this->assertTrue($managers->contains('id', $deptManagerUser->id));
        $this->assertTrue($managers->contains('id', $newPmUser->id));
        $this->assertTrue($managers->contains('id', $pm2User->id));

        // IFS Manager, older superseded PM, and regular member should be excluded
        $this->assertFalse($managers->contains('id', $ifsUser->id));
        $this->assertFalse($managers->contains('id', $oldPmUser->id));
        $this->assertFalse($managers->contains('id', $memberUser->id));
    }

    public function test_get_role_attribute_for_project_manager(): void
    {
        $pmRbs = TbRbs::firstOrCreate(['id' => 'P2211'], ['name' => 'Project Manager', 'showing_name' => 'Project Manager']);
        $proj = TbProject::factory()->create(['no_project' => 'PRJ-201']);

        $oldPmUser = User::factory()->create();
        $newPmUser = User::factory()->create();

        TbAssignProject::create([
            'npk' => $oldPmUser->employee_id,
            'no_project' => $proj->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2025-01-01 00:00:00',
        ]);
        TbAssignProject::create([
            'npk' => $newPmUser->employee_id,
            'no_project' => $proj->no_project,
            'id_rbs' => $pmRbs->id,
            'start_date' => '2026-01-01 00:00:00',
        ]);

        $this->assertEquals('manager', $newPmUser->fresh()->role);
        $this->assertEquals('user', $oldPmUser->fresh()->role);
    }

    public function test_get_users_by_role_in_local_env(): void
    {
        $testDeptManager = User::factory()->create();
        $testDeptOrg = HrdOrgchart::find($testDeptManager->orgchart_id);
        $testDeptOrg->update([
            'employee_id' => $testDeptManager->id,
            'org_code' => 'TEST-DEPT',
        ]);

        $regularDeptManager = User::factory()->create();
        $regularDeptOrg = HrdOrgchart::find($regularDeptManager->orgchart_id);
        $regularDeptOrg->update([
            'employee_id' => $regularDeptManager->id,
            'org_code' => 'HRD',
        ]);

        $originalEnv = $this->app['env'];
        try {
            $this->app['env'] = 'local';

            $ifsManagers = User::getUsersByRole('ifs_manager');
            $this->assertTrue($ifsManagers->contains('id', $testDeptManager->id));
            $this->assertFalse($ifsManagers->contains('id', $regularDeptManager->id));

            $managers = User::getUsersByRole('manager');
            $this->assertFalse($managers->contains('id', $testDeptManager->id));
            $this->assertTrue($managers->contains('id', $regularDeptManager->id));
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
