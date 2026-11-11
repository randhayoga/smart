<?php

namespace Tests\Feature\Smart\Admin;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Inventory\Asset;
use App\Models\Inventory\AssetActivationApproval;
use App\Models\Inventory\AssetDeletionApproval;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Feature & Query-Count tests for Phase 1 Performance Optimization across Inventory, Access Management, and Approval controllers.
 */
class Phase1PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->superadmin = User::factory()->create(['employee_id' => '265656']);
        $this->superadmin->assignRole('superadmin');

        $this->admin = User::factory()->create(['employee_id' => '255578']);
        $this->admin->assignRole('admin_cfs');
    }

    public function test_non_consumable_index_uses_flat_queries_and_eliminates_duplicate_payload(): void
    {
        $building = Location::create(['name' => 'HQ Building', 'parent_id' => null]);
        $floor = Location::create(['name' => 'Floor 3', 'parent_id' => $building->id]);
        $room = Location::create(['name' => 'IT Room', 'parent_id' => $floor->id]);

        $category = Category::factory()->create(['code' => 'COMP', 'name' => 'Computer']);
        $type = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'COMP-LP',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);
        $brand = Brand::factory()->create(['name' => 'ThinkPad']);
        $uom = Uom::factory()->create(['name' => 'Unit']);
        $model = InventoryModel::factory()->create([
            'number' => 'COMP-LP-0001',
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad X1 Carbon',
            'specification' => 'Core i7, 32GB RAM',
        ]);

        $organizer = Organizer::factory()->create(['name' => 'IFS']);
        $vendor = Vendor::factory()->create(['name' => 'PT Lenovo Indonesia']);
        $project = TbProject::create([
            'no_project' => 'PRJ-OPT-100',
            'project_name' => 'Optimization Rollout',
            'client_id' => 'CLI-OPT',
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-2026-COMP-001',
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $room->id,
            'unit_price' => 25000000,
        ]);

        for ($i = 1; $i <= 15; $i++) {
            Asset::factory()->create([
                'lot_id' => $lot->id,
                'location_id' => $room->id,
                'number' => sprintf('000%02d-COMP-LP-IFS-PTRE-26', $i),
                'legacy_number' => sprintf('LEG-%02d', $i),
                'status' => 'Tersedia',
                'condition' => 'Bagus',
                'burden' => 'Project',
                'project_id' => $project->id_project,
                'comp_specification' => 'Custom RAM 32GB',
                'price' => 25000000,
            ]);
        }

        DB::connection('SMART')->flushQueryLog();
        DB::connection('SMART')->enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('smart.inventory.non-consumables'));
        $response->assertOk();

        $smartQueries = count(DB::connection('SMART')->getQueryLog());
        $this->assertLessThanOrEqual(18, $smartQueries, "Expected <= 18 SMART queries on NonConsumableController@index, got {$smartQueries}");

        $pageProps = $response->viewData('page')['props'];
        $this->assertArrayHasKey('units_compact', $pageProps);
        $this->assertArrayNotHasKey('assets', $pageProps, 'Duplicate top-level "assets" prop must not be serialized');
        $this->assertArrayNotHasKey('projects', $pageProps, 'projects must be lazy-loaded on modal open');
        $this->assertArrayNotHasKey('users', $pageProps, 'users must be lazy-loaded on modal open');

        $compact = $pageProps['units_compact'];
        $this->assertCount(15, $compact['rows']);
        $this->assertArrayHasKey($lot->id, $compact['lots']);
        $this->assertArrayHasKey($room->id, $compact['locs']);

        $lotEntry = $compact['lots'][$lot->id];
        $this->assertEquals('ThinkPad X1 Carbon', $lotEntry['barang_nama']);
        $this->assertEquals('ThinkPad', $lotEntry['barang_brand']);
        $this->assertEquals('Computer', $lotEntry['barang_category']);
        $this->assertEquals('COMP', $lotEntry['barang_category_code']);
        $this->assertEquals('Laptop', $lotEntry['barang_subcategory']);
        $this->assertEquals('IFS', $lotEntry['organizer']);
        $this->assertEquals('PT Lenovo Indonesia', $lotEntry['vendor']);

        $locEntry = $compact['locs'][$room->id];
        $this->assertEquals('HQ Building, Floor 3, IT Room', $locEntry);
        $this->assertEquals($room->id, $compact['rows'][0][4], 'Compact row tuple must preserve actual leaf location_id for hierarchical filtering');
        $this->assertEquals('PRJ-OPT-100', $compact['projects'][$project->id_project]);

        // Also verify getFlatUnits() preserves actual leaf location_id and non-duplicated full_name
        $flatUnits = app(\App\Services\Inventory\StockInventoryQueryService::class)->getFlatUnits(['lot_id' => $lot->id]);
        $this->assertEquals('HQ Building, Floor 3, IT Room', $flatUnits[0]['location']);
        $this->assertEquals($room->id, $flatUnits[0]['location_id']);
        $this->assertArrayNotHasKey('floor', $flatUnits[0]);
        $this->assertArrayNotHasKey('room', $flatUnits[0]);
    }

    public function test_stock_management_index_calculates_aggregates_without_hydrating_assets(): void
    {
        $category = Category::factory()->create(['code' => 'FUR', 'name' => 'Furniture']);
        $nonConsType = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'FUR-CH',
            'name' => 'Chair',
            'is_consumable' => false,
        ]);
        $consType = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'ATK-PN',
            'name' => 'Pen',
            'is_consumable' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'Ergotec']);
        $uom = Uom::factory()->create(['name' => 'Pcs']);

        $nonConsModel = InventoryModel::factory()->create([
            'number' => 'FUR-CH-001',
            'type_id' => $nonConsType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Office Chair',
        ]);
        $consModel = InventoryModel::factory()->create([
            'number' => 'ATK-PN-001',
            'type_id' => $consType->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Ballpoint Pen',
        ]);

        $lotNonCons = Lot::factory()->create(['model_id' => $nonConsModel->id]);
        Asset::factory()->count(4)->create([
            'lot_id' => $lotNonCons->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);
        Asset::factory()->count(2)->create([
            'lot_id' => $lotNonCons->id,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
        ]);

        Lot::factory()->create([
            'model_id' => $consModel->id,
            'initial_quantity' => 50,
            'current_quantity' => 35,
        ]);

        DB::connection('SMART')->flushQueryLog();
        DB::connection('SMART')->enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('smart.inventory'));
        $response->assertOk();

        $smartQueries = count(DB::connection('SMART')->getQueryLog());
        $this->assertLessThanOrEqual(18, $smartQueries, "Expected <= 18 SMART queries on StockManagementController@index, got {$smartQueries}");

        $barangs = collect($response->viewData('page')['props']['barangs']);
        $nonConsRow = $barangs->firstWhere('id', $nonConsModel->id);
        $consRow = $barangs->firstWhere('id', $consModel->id);

        $this->assertNotNull($nonConsRow);
        $this->assertEquals(6, $nonConsRow['amount']);

        $this->assertNotNull($consRow);
        $this->assertEquals(35, $consRow['amount']);
    }

    public function test_access_management_index_executes_in_constant_queries_without_n_plus_one(): void
    {
        User::factory()->count(25)->create(['active' => 1]);

        foreach (['SMART', 'user_hris', 'reportal'] as $conn) {
            DB::connection($conn)->flushQueryLog();
            DB::connection($conn)->enableQueryLog();
        }

        $response = $this->actingAs($this->superadmin)->get(route('smart.access.index'));
        $response->assertOk();

        $smartQueries = count(DB::connection('SMART')->getQueryLog());
        $hrisQueries = count(DB::connection('user_hris')->getQueryLog());
        $reportalQueries = count(DB::connection('reportal')->getQueryLog());

        $this->assertLessThanOrEqual(15, $smartQueries, "Expected <= 15 SMART queries on AccessManagementController@index, got {$smartQueries}");
        $this->assertLessThanOrEqual(5, $hrisQueries, "Expected <= 5 HRIS queries on AccessManagementController@index, got {$hrisQueries}");
        $this->assertLessThanOrEqual(2, $reportalQueries, "Expected <= 2 Reportal queries on AccessManagementController@index, got {$reportalQueries}");
    }

    public function test_manager_activation_and_deletion_approval_pages_avoid_n_plus_one_queries(): void
    {
        $ifsManager = User::factory()->create(['employee_id' => '888111']);
        $ifsManager->assignRole('ifs_manager');

        $lot = Lot::factory()->create();
        for ($i = 1; $i <= 8; $i++) {
            $asset = Asset::factory()->create([
                'lot_id' => $lot->id,
                'status' => 'Belum Diverifikasi',
                'approval_status' => 'Pending:DM',
            ]);
            AssetActivationApproval::create([
                'asset_id' => $asset->id,
                'requester_id' => $this->admin->id,
                'proposed_status' => 'Tersedia',
                'proposed_condition' => 'Bagus',
                'decision' => 'pending',
                'requested_at' => now(),
            ]);
            AssetDeletionApproval::create([
                'asset_id' => $asset->id,
                'requester_id' => $this->admin->id,
                'proposed_condition' => 'Rusak Total',
                'previous_condition' => 'Bagus',
                'previous_status' => 'Tersedia',
                'decision' => 'pending',
                'requested_at' => now(),
            ]);
        }

        DB::connection('SMART')->flushQueryLog();
        DB::connection('SMART')->enableQueryLog();

        $actResponse = $this->actingAs($ifsManager)->get(route('smart.approve-activation'));
        $actResponse->assertOk();
        $actQueries = count(DB::connection('SMART')->getQueryLog());
        $this->assertLessThanOrEqual(25, $actQueries, "Expected <= 25 SMART queries on ManagerAssetActivationApprovalController@index, got {$actQueries}");

        DB::connection('SMART')->flushQueryLog();
        $delResponse = $this->actingAs($ifsManager)->get(route('smart.approve-deletion'));
        $delResponse->assertOk();
        $delQueries = count(DB::connection('SMART')->getQueryLog());
        $this->assertLessThanOrEqual(25, $delQueries, "Expected <= 25 SMART queries on ManagerAssetDeletionApprovalController@index, got {$delQueries}");
    }
}
