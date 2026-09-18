<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HrdEmployee;
use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for ConsumableRequestOptionController providing dialog options.
 */
class ConsumableRequestOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->create();
    }

    private function createRequester(): User
    {
        return User::factory()->create([
            'name' => 'Budi Santoso',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_options_endpoint(): void
    {
        $this->getJson(route('smart.inventory.consumables.request-options'))->assertUnauthorized();
    }

    public function test_options_endpoint_returns_users_departments_and_projects(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'IT Infrastructure']);
        $project = TbProject::factory()->create(['project_name' => 'SMART Project']);

        $response = $this->actingAs($admin)->getJson(route('smart.inventory.consumables.request-options'));
        $response->assertOk();
        $response->assertJsonStructure([
            'users' => [['id', 'name', 'employee_id']],
            'departments' => [['id', 'name']],
            'projects' => [['id', 'name', 'no_project']],
        ]);

        $response->assertJsonFragment(['id' => $requester->id]);
        $response->assertJsonFragment(['id' => $orgchart->id]);
        $response->assertJsonFragment(['id' => $project->id_project]);
    }

    public function test_options_endpoint_filters_by_user_id(): void
    {
        $admin = $this->createAdmin();
        $requester = $this->createRequester();
        $orgchart = HrdOrgchart::factory()->create(['org_name' => 'Finance Dept', 'org_code' => 'FIN']);
        $employee = HrdEmployee::factory()->create([
            'employee_id' => $requester->username,
            'orgchart_id' => $orgchart->id,
        ]);

        $projectAssigned = TbProject::factory()->create(['no_project' => 'PRJ-01', 'project_name' => 'Assigned Project']);
        $projectUnassigned = TbProject::factory()->create(['no_project' => 'PRJ-02', 'project_name' => 'Unassigned Project']);

        TbAssignProject::create([
            'npk' => $requester->username,
            'no_project' => 'PRJ-01',
            'id_rbs' => 'P1221',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'DELETION' => '0',
        ]);

        $response = $this->actingAs($admin)->getJson(route('smart.inventory.consumables.request-options', ['user_id' => $requester->id]));
        $response->assertOk();

        // Department should only contain the requester's department
        $departments = $response->json('departments');
        $this->assertCount(1, $departments);
        $this->assertEquals($orgchart->id, $departments[0]['id']);

        // Projects should only contain the assigned project
        $projects = $response->json('projects');
        $this->assertCount(1, $projects);
        $this->assertEquals($projectAssigned->id_project, $projects[0]['id']);
    }
}
