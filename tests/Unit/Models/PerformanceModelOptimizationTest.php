<?php

namespace Tests\Unit\Models;

use App\Models\Auth\Role;
use App\Models\HrdOrgchart;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests verifying Phase 1 core model performance optimizations:
 * - Location::allWithFullName() and Location::hierarchyMap() zero-N+1 hierarchy resolution
 * - TbProject::optionsForSelection() and TbProject::projectNumberMap() lightweight queries
 * - User memoized dynamic role lookup sets and batch role preloading
 * - Removal of unintended cascading global $with on Asset, Lot, and Inventory\Model
 */
class PerformanceModelOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.disable_test_admin_bypass' => true]);
        User::clearRoleLookupCache();
    }

    protected function tearDown(): void
    {
        User::clearRoleLookupCache();
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }

    public function test_location_all_with_full_name_computes_hierarchy_in_one_query_and_serializes_with_zero_queries(): void
    {
        $building = Location::create(['name' => 'Gedung A', 'parent_id' => null]);
        $floor = Location::create(['name' => 'Lantai 2', 'parent_id' => $building->id]);
        $room = Location::create(['name' => 'Ruang Server', 'parent_id' => $floor->id]);

        DB::connection('SMART')->flushQueryLog();
        DB::connection('SMART')->enableQueryLog();

        $locations = Location::allWithFullName();

        $queriesAfterFetch = count(DB::connection('SMART')->getQueryLog());
        $this->assertEquals(1, $queriesAfterFetch, 'Expected exactly 1 SQL query to load all locations with full_name');

        // Verify JSON serialization triggers 0 additional queries
        DB::connection('SMART')->flushQueryLog();
        $json = json_encode($locations);
        $this->assertNotFalse($json);
        $this->assertCount(0, DB::connection('SMART')->getQueryLog(), 'Expected 0 SQL queries during json_encode($locations)');

        $byId = $locations->keyBy('id');
        $this->assertEquals('Gedung A', $byId[$building->id]->full_name);
        $this->assertEquals('Gedung A, Lantai 2', $byId[$floor->id]->full_name);
        $this->assertEquals('Gedung A, Lantai 2, Ruang Server', $byId[$room->id]->full_name);

        // Verify hierarchy map resolves building, floor, and room accurately
        $hierarchyMap = Location::hierarchyMap($locations);
        $this->assertEquals([
            'building_id' => $building->id,
            'building_name' => 'Gedung A',
            'floor_id' => null,
            'floor_name' => null,
            'room_id' => null,
            'room_name' => null,
            'full_name' => 'Gedung A',
            'is_active' => true,
        ], $hierarchyMap[$building->id]);

        $this->assertEquals([
            'building_id' => $building->id,
            'building_name' => 'Gedung A',
            'floor_id' => $floor->id,
            'floor_name' => 'Lantai 2',
            'room_id' => null,
            'room_name' => null,
            'full_name' => 'Gedung A, Lantai 2',
            'is_active' => true,
        ], $hierarchyMap[$floor->id]);

        $this->assertEquals([
            'building_id' => $building->id,
            'building_name' => 'Gedung A',
            'floor_id' => $floor->id,
            'floor_name' => 'Lantai 2',
            'room_id' => $room->id,
            'room_name' => 'Ruang Server',
            'full_name' => 'Gedung A, Lantai 2, Ruang Server',
            'is_active' => true,
        ], $hierarchyMap[$room->id]);
    }

    public function test_tb_project_options_for_selection_and_number_map_use_single_lightweight_query(): void
    {
        $project = TbProject::create([
            'no_project' => 'PRJ-2026-001',
            'project_name' => 'Smart Optimization Project',
            'client_id' => 'CLI-01',
        ]);

        DB::connection('reportal')->flushQueryLog();
        DB::connection('reportal')->enableQueryLog();

        $options = TbProject::optionsForSelection();
        $this->assertCount(1, DB::connection('reportal')->getQueryLog());
        $this->assertNotEmpty($options);

        $first = collect($options)->firstWhere('id', $project->id_project);
        $this->assertNotNull($first);
        $this->assertEquals($project->id_project, $first['id']);
        $this->assertEquals($project->id_project, $first['id_project']);
        $this->assertEquals('PRJ-2026-001', $first['no_project']);
        $this->assertEquals('Smart Optimization Project', $first['project_name']);

        $map = TbProject::projectNumberMap($options);
        $this->assertEquals('PRJ-2026-001', $map[$project->id_project]);
    }

    public function test_user_role_resolution_memoizes_lookup_sets_and_prevents_n_plus_one(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']);
        Role::firstOrCreate(['name' => 'user'], ['label' => 'User']);

        $ifsManager = User::factory()->create(['employee_id' => '800001']);
        $deptManager = User::factory()->create(['employee_id' => '800002']);
        $projectManager = User::factory()->create(['employee_id' => '800003']);
        $regularUsers = User::factory()->count(15)->create();

        HrdOrgchart::create([
            'org_code' => User::getIfsOrgCode(),
            'org_name' => 'IFS Department',
            'employee_id' => (int) $ifsManager->employee_id,
        ]);

        HrdOrgchart::create([
            'org_code' => 'ENG-DEPT',
            'org_name' => 'Engineering Department',
            'employee_id' => (int) $deptManager->employee_id,
        ]);

        TbAssignProject::create([
            'no_project' => 'PRJ-PM-01',
            'npk' => $projectManager->employee_id,
            'id_rbs' => 'P2211',
            'start_date' => '2026-01-01',
        ]);

        $allUsers = collect([$ifsManager, $deptManager, $projectManager])->merge($regularUsers);

        foreach (['SMART', 'user_hris', 'reportal'] as $conn) {
            DB::connection($conn)->flushQueryLog();
            DB::connection($conn)->enableQueryLog();
        }

        User::preloadRolesFor($allUsers);

        foreach ($allUsers as $u) {
            $_ = $u->role;
        }

        $smartQueries = count(DB::connection('SMART')->getQueryLog());
        $hrisQueries = count(DB::connection('user_hris')->getQueryLog());
        $reportalQueries = count(DB::connection('reportal')->getQueryLog());

        // Regardless of having 18 users, total queries across all 3 connections should be <= 5
        $this->assertLessThanOrEqual(2, $smartQueries, "Expected <= 2 SMART queries, got {$smartQueries}");
        $this->assertLessThanOrEqual(2, $hrisQueries, "Expected <= 2 HRIS queries, got {$hrisQueries}");
        $this->assertLessThanOrEqual(1, $reportalQueries, "Expected <= 1 Reportal query, got {$reportalQueries}");

        $this->assertEquals('ifs_manager', $ifsManager->role);
        $this->assertEquals('manager', $deptManager->role);
        $this->assertEquals('manager', $projectManager->role);
        $this->assertEquals('user', $regularUsers->first()->role);
    }

    public function test_asset_lot_and_model_do_not_cascade_global_eager_loads_by_default(): void
    {
        $category = Category::factory()->create();
        $type = Subcategory::factory()->create(['category_id' => $category->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();
        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
        ]);
        Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
        ]);

        DB::connection('SMART')->flushQueryLog();
        DB::connection('SMART')->enableQueryLog();

        $fetchedAsset = Asset::query()->first();
        $this->assertNotNull($fetchedAsset);
        $this->assertCount(1, DB::connection('SMART')->getQueryLog(), 'Asset::query()->first() should execute 1 query without cascading $with');
        $this->assertFalse($fetchedAsset->relationLoaded('lot'));
        $this->assertFalse($fetchedAsset->relationLoaded('location'));
    }
}
