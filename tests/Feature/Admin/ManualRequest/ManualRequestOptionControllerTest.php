<?php

namespace Tests\Feature\Admin\ManualRequest;

use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRequestOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $dm;
    private User $pm;
    private User $employee;
    private HrdOrgchart $orgchart;
    private TbProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dm = User::factory()->create([
            'employee_id' => 'NPK-DM01',
            'employee_name' => 'Budi DM',
            'active' => 1,
        ]);

        $this->orgchart = HrdOrgchart::create([
            'org_code' => 'ORG-ENG55',
            'org_name' => 'Engineering Department',
            'employee_id' => $this->dm->id,
        ]);

        $this->dm->update(['orgchart_id' => $this->orgchart->id]);

        $this->pm = User::factory()->create([
            'employee_id' => 'NPK-PM01',
            'employee_name' => 'Siti PM',
            'orgchart_id' => $this->orgchart->id,
            'active' => 1,
        ]);

        $this->employee = User::factory()->create([
            'employee_id' => 'NPK-EMP01',
            'employee_name' => 'Andi Staff',
            'orgchart_id' => $this->orgchart->id,
            'active' => 1,
        ]);

        $this->admin = User::factory()->create([
            'employee_id' => 'NPK-ADM01',
            'employee_name' => 'Admin Staff',
            'orgchart_id' => $this->orgchart->id,
            'active' => 1,
        ]);

        $this->project = TbProject::create([
            'no_project' => 'PRJ-701',
            'project_name' => 'Geothermal Plant Phase 1',
            'client_id' => '10',
            'status' => '1',
            'DELETION' => '0',
        ]);

        TbAssignProject::create([
            'no_project' => 'PRJ-701',
            'npk' => 'NPK-EMP01',
            'id_rbs' => 'ENG01',
            'start_date' => '2026-01-01',
            'DELETION' => '0',
        ]);

        TbAssignProject::create([
            'no_project' => 'PRJ-701',
            'npk' => 'NPK-PM01',
            'id_rbs' => 'P2211',
            'start_date' => '2026-02-01',
            'DELETION' => '0',
        ]);
    }

    public function test_returns_active_employees_formatted_with_employee_id_and_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-options'));

        $response->assertOk()
            ->assertJsonStructure(['employees']);

        $employees = collect($response->json('employees'));
        $emp = $employees->firstWhere('id', $this->employee->id);
        $this->assertNotNull($emp);
        $this->assertEquals('NPK-EMP01 - Andi Staff', $emp['name']);
    }

    public function test_hydrates_department_projects_and_corporate_dm_for_selected_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-options', ['user_id' => $this->employee->id]));

        $response->assertOk();
        $this->assertCount(1, $response->json('departments'));
        $this->assertEquals($this->orgchart->id, $response->json('departments.0.id'));
        $this->assertEquals('Engineering Department', $response->json('departments.0.name'));

        $this->assertCount(1, $response->json('projects'));
        $this->assertEquals($this->project->id_project, $response->json('projects.0.id'));

        $this->assertEquals($this->dm->id, $response->json('auto_approver_id'));
        $candidateIds = collect($response->json('approver_candidates'))->pluck('id')->all();
        $this->assertContains($this->dm->id, $candidateIds);
        $this->assertContains($this->employee->id, $candidateIds);
    }

    public function test_resolves_project_pm_and_project_employees_when_project_id_provided(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-options', ['project_id' => $this->project->id_project]));

        $response->assertOk();
        $this->assertEquals($this->pm->id, $response->json('auto_approver_id'));

        $candidateIds = collect($response->json('approver_candidates'))->pluck('id')->all();
        $this->assertContains($this->pm->id, $candidateIds);
        $this->assertContains($this->employee->id, $candidateIds);
    }
}
