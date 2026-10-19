<?php

namespace Tests\Unit\Models;

use App\Models\TbProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TbProjectScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_valid_for_selection_only_returns_projects_with_number_and_name(): void
    {
        // 1. Valid project
        $valid = TbProject::factory()->create([
            'no_project' => 'PRJ-TEST-01',
            'project_name' => 'Valid Project',
        ]);

        // 2. Missing no_project (null)
        $noNumberNull = TbProject::factory()->create([
            'no_project' => null,
            'project_name' => 'Project Without Number Null',
        ]);

        // 3. Empty no_project ('')
        $noNumberEmpty = TbProject::factory()->create([
            'no_project' => '',
            'project_name' => 'Project Without Number Empty',
        ]);

        // 4. Missing project_name (null)
        $noNameNull = TbProject::factory()->create([
            'no_project' => 'PRJ-TEST-02',
            'project_name' => null,
        ]);

        // 5. Empty project_name ('')
        $noNameEmpty = TbProject::factory()->create([
            'no_project' => 'PRJ-TEST-03',
            'project_name' => '',
        ]);

        $results = TbProject::validForSelection()->get();

        $this->assertTrue($results->contains('id_project', $valid->id_project));
        $this->assertFalse($results->contains('id_project', $noNumberNull->id_project));
        $this->assertFalse($results->contains('id_project', $noNumberEmpty->id_project));
        $this->assertFalse($results->contains('id_project', $noNameNull->id_project));
        $this->assertFalse($results->contains('id_project', $noNameEmpty->id_project));
    }
}
