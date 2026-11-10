<?php

namespace Tests\Unit\Services;

use App\Models\HrdOrgchart;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use App\Models\Request\Request as SmartRequest;
use App\Models\TbProject;
use App\Models\User;
use App\Services\Inventory\ConsumableMovementQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumableMovementQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsumableMovementQueryService $service;
    private User $user;
    private Category $categoryConsumable;
    private Category $categoryNonConsumable;
    private Type $typePaper;
    private Type $typePen;
    private Type $typeLaptop;
    private InventoryModel $modelHvs;
    private InventoryModel $modelPen;
    private InventoryModel $modelLaptop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConsumableMovementQueryService();

        $this->user = User::factory()->create();

        // Setup master data
        $this->categoryConsumable = Category::factory()->create(['name' => 'Stationery', 'code' => 'STN']);
        $this->categoryNonConsumable = Category::factory()->create(['name' => 'IT Equipment', 'code' => 'ITE']);

        $this->typePaper = Type::factory()->create([
            'category_id' => $this->categoryConsumable->id,
            'name' => 'Paper',
            'code' => 'PPR',
            'is_consumable' => true,
        ]);
        $this->typePen = Type::factory()->create([
            'category_id' => $this->categoryConsumable->id,
            'name' => 'Pen',
            'code' => 'PEN',
            'is_consumable' => true,
        ]);
        $this->typeLaptop = Type::factory()->create([
            'category_id' => $this->categoryNonConsumable->id,
            'name' => 'Laptop',
            'code' => 'LPT',
            'is_consumable' => false,
        ]);

        $brand = Brand::factory()->create(['name' => 'Standard']);
        $uom = Uom::factory()->create(['name' => 'Pcs']);

        $this->modelHvs = InventoryModel::factory()->create([
            'type_id' => $this->typePaper->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'HVS A4 Paper',
            'number' => 'MDL-HVS-001',
        ]);
        $this->modelPen = InventoryModel::factory()->create([
            'type_id' => $this->typePen->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Ballpoint Black',
            'number' => 'MDL-PEN-001',
        ]);
        $this->modelLaptop = InventoryModel::factory()->create([
            'type_id' => $this->typeLaptop->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'ThinkPad T14',
            'number' => 'MDL-LPT-001',
        ]);
    }

    public function test_get_filter_options_returns_consumables_departments_and_projects(): void
    {
        $options = $this->service->getFilterOptions();

        $this->assertArrayHasKey('categories', $options);
        $this->assertArrayHasKey('types', $options);
        $this->assertArrayHasKey('models', $options);
        $this->assertArrayHasKey('departments', $options);
        $this->assertArrayHasKey('projects', $options);
        $this->assertArrayHasKey('availableDecades', $options);
        $this->assertArrayHasKey('availableYears', $options);

        // Should include Stationery, but not IT Equipment
        $categoryIds = collect($options['categories'])->pluck('id')->all();
        $this->assertContains($this->categoryConsumable->id, $categoryIds);
        $this->assertNotContains($this->categoryNonConsumable->id, $categoryIds);

        // Types should include Paper and Pen, but not Laptop
        $typeIds = collect($options['types'])->pluck('id')->all();
        $this->assertContains($this->typePaper->id, $typeIds);
        $this->assertContains($this->typePen->id, $typeIds);
        $this->assertNotContains($this->typeLaptop->id, $typeIds);

        // Models should include HVS and Pen, but not Laptop
        $modelIds = collect($options['models'])->pluck('id')->all();
        $this->assertContains($this->modelHvs->id, $modelIds);
        $this->assertContains($this->modelPen->id, $modelIds);
        $this->assertNotContains($this->modelLaptop->id, $modelIds);
    }

    public function test_get_movement_metrics_with_total_period(): void
    {
        // 1. Inbound: 100 HVS in March 2026
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 100,
            'created_at' => Carbon::create(2026, 3, 10, 10, 0, 0),
        ]);

        // 2. Outbound: 30 HVS in March 2026
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_out',
            'quantity_change' => -30,
            'created_at' => Carbon::create(2026, 3, 15, 14, 0, 0),
        ]);

        // 3. Inbound: 50 Pens in April 2026
        InventoryLog::create([
            'model_id' => $this->modelPen->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 50,
            'created_at' => Carbon::create(2026, 4, 1, 9, 0, 0),
        ]);

        // 4. Non-consumable log (should be ignored)
        InventoryLog::create([
            'model_id' => $this->modelLaptop->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 5,
            'created_at' => Carbon::create(2026, 3, 1, 9, 0, 0),
        ]);

        $metrics = $this->service->getMovementMetrics(['year' => 'all']);

        $this->assertEquals(150, $metrics['summary']['total_in']);
        $this->assertEquals(30, $metrics['summary']['total_out']);
        $this->assertEquals(2, $metrics['summary']['total_in_events']);
        $this->assertEquals(1, $metrics['summary']['total_out_events']);
        $this->assertEquals(120, $metrics['summary']['net_movement']);
        $this->assertEquals(3, $metrics['summary']['total_events']);

        $this->assertCount(3, $metrics['logs']);
        $this->assertArrayHasKey('barang_number', $metrics['logs'][0]);
        $this->assertArrayHasKey('barang_name', $metrics['logs'][0]);
        $this->assertArrayHasKey('waktu', $metrics['logs'][0]);
    }

    public function test_get_movement_metrics_omitted_year_defaults_to_selected_decade_overview(): void
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $currentMonth = $now->month;
        $currentDecade = (int) (floor($currentYear / 10) * 10);

        // Current month log
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 70,
            'created_at' => Carbon::create($currentYear, $currentMonth, 1, 10, 0, 0),
        ]);

        // Past year log in same decade
        $pastYearInDecade = $currentYear > $currentDecade ? $currentYear - 1 : $currentYear;
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 100,
            'created_at' => Carbon::create($pastYearInDecade, 1, 1, 10, 0, 0),
        ]);

        // Omit year and month filters -> should default to current decade overview with 10 yearly trend points
        $metrics = $this->service->getMovementMetrics([]);

        $this->assertEquals(170, $metrics['summary']['total_in']);
        $this->assertEquals('', $metrics['filters']['year']);
        $this->assertEquals('', $metrics['filters']['month']);
        $this->assertEquals((string) $currentDecade, $metrics['filters']['decade']);
        $this->assertCount(10, $metrics['trends']);

        // Explicit year without month -> aggregates entire year with 12 monthly trend points
        $yearMetrics = $this->service->getMovementMetrics(['year' => $currentYear]);
        $this->assertEquals((string) $currentYear, $yearMetrics['filters']['year']);
        $this->assertEquals('', $yearMetrics['filters']['month']);
        $this->assertCount(12, $yearMetrics['trends']);
    }

    public function test_get_movement_metrics_filtered_by_utilization_and_department(): void
    {
        $lotCorporate = Lot::factory()->create([
            'barang_id' => $this->modelHvs->id,
            'burden' => 'Corporate',
        ]);
        $lotProject = Lot::factory()->create([
            'barang_id' => $this->modelHvs->id,
            'burden' => 'Project',
            'project_id' => 999,
        ]);

        // 1. Inbound: 100 and 50 stock in (Total stock in = 150)
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'lot_id' => $lotCorporate->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 100,
            'created_at' => Carbon::create(2026, 3, 1, 10, 0, 0),
        ]);

        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'lot_id' => $lotProject->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 50,
            'created_at' => Carbon::create(2026, 3, 1, 11, 0, 0),
        ]);

        // 2. Outbound: Corporate Request (30 items, org_id = 10)
        $corpReq = SmartRequest::create([
            'request_number' => '032026-0001',
            'user_id' => $this->user->id,
            'approver_id' => $this->user->id,
            'utilization' => 'corporate',
            'org_id' => 10,
            'status' => 'success',
        ]);
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'lot_id' => $lotCorporate->id,
            'request_id' => $corpReq->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_out',
            'quantity_change' => -30,
            'created_at' => Carbon::create(2026, 3, 5, 14, 0, 0),
        ]);

        // 3. Outbound: Project Request (20 items, project_id = 999)
        $projReq = SmartRequest::create([
            'request_number' => '032026-0002',
            'user_id' => $this->user->id,
            'approver_id' => $this->user->id,
            'utilization' => 'project',
            'project_id' => 999,
            'status' => 'success',
        ]);
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'lot_id' => $lotProject->id,
            'request_id' => $projReq->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_out',
            'quantity_change' => -20,
            'created_at' => Carbon::create(2026, 3, 8, 15, 0, 0),
        ]);

        // Filtering by Corporate: incoming stock is NOT filtered (150), only corporate stock out (30)
        $corporateMetrics = $this->service->getMovementMetrics([
            'year' => 2026,
            'month' => 3,
            'utilization' => 'corporate',
        ]);
        $this->assertEquals(150, $corporateMetrics['summary']['total_in']);
        $this->assertEquals(30, $corporateMetrics['summary']['total_out']);

        // Filtering by Corporate Department (org_id 10)
        $deptMetrics = $this->service->getMovementMetrics([
            'year' => 2026,
            'month' => 3,
            'utilization' => 'corporate',
            'department_id' => 10,
        ]);
        $this->assertEquals(150, $deptMetrics['summary']['total_in']);
        $this->assertEquals(30, $deptMetrics['summary']['total_out']);

        // Filtering by Project (project_id 999): incoming stock is NOT filtered (150), only project stock out (20)
        $projectMetrics = $this->service->getMovementMetrics([
            'year' => 2026,
            'month' => 3,
            'utilization' => 'project',
            'project_id' => 999,
        ]);
        $this->assertEquals(150, $projectMetrics['summary']['total_in']);
        $this->assertEquals(20, $projectMetrics['summary']['total_out']);
    }

    public function test_get_movement_metrics_with_custom_decade_filters_correctly(): void
    {
        // 2018 is in 2010 decade
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 50,
            'created_at' => Carbon::create(2018, 6, 1, 10, 0, 0),
        ]);

        // 2026 is in 2020 decade
        InventoryLog::create([
            'model_id' => $this->modelHvs->id,
            'user_id' => $this->user->id,
            'action_type' => 'stock_in',
            'quantity_change' => 70,
            'created_at' => Carbon::create(2026, 3, 1, 10, 0, 0),
        ]);

        $metrics2010 = $this->service->getMovementMetrics(['decade' => 2010]);

        $this->assertEquals(50, $metrics2010['summary']['total_in']);
        $this->assertEquals('2010', $metrics2010['filters']['decade']);
        $this->assertEquals('', $metrics2010['filters']['year']);
        $this->assertCount(10, $metrics2010['trends']);
        $this->assertEquals(2010, $metrics2010['trends'][0]['period']);
        $this->assertEquals('2010', $metrics2010['trends'][0]['label']);
        $this->assertEquals(2019, $metrics2010['trends'][9]['period']);
        $this->assertEquals('2019', $metrics2010['trends'][9]['label']);
    }
}
