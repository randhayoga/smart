<?php

namespace Tests\Feature;

use App\Models\HrdEmployee;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Category;
use App\Models\Master\Type;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardConsumableMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private InventoryModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $adminEmployee = HrdEmployee::factory()->create(['employee_id' => '255578']);
        $this->adminUser = User::factory()->create(['employee_id' => $adminEmployee->employee_id]);

        $cat = Category::factory()->create(['name' => 'Consumables', 'code' => 'CSM']);
        $type = Type::factory()->create([
            'category_id' => $cat->id,
            'name' => 'Ink Cartridge',
            'code' => 'INK',
            'is_consumable' => true,
        ]);
        $brand = Brand::factory()->create(['name' => 'Epson']);
        $uom = Uom::factory()->create(['name' => 'Unit']);

        $this->model = InventoryModel::factory()->create([
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Epson 003 Black',
            'number' => 'MDL-EPS-001',
        ]);

        InventoryLog::create([
            'model_id' => $this->model->id,
            'user_id' => $this->adminUser->id,
            'action_type' => 'stock_in',
            'quantity_change' => 50,
            'created_at' => Carbon::create(2026, 2, 1, 10, 0, 0),
        ]);

        InventoryLog::create([
            'model_id' => $this->model->id,
            'user_id' => $this->adminUser->id,
            'action_type' => 'stock_out',
            'quantity_change' => -10,
            'created_at' => Carbon::create(2026, 2, 5, 12, 0, 0),
        ]);
    }

    public function test_admin_dashboard_provides_stock_movement_filter_options(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('smart.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/MultiRoles/Dashboard')
            ->has('stockMovementFilters.categories')
            ->has('stockMovementFilters.types')
            ->has('stockMovementFilters.models')
            ->has('stockMovementFilters.availableYears')
        );
    }

    public function test_admin_can_fetch_stock_movement_data_via_partial_reload(): void
    {
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $response = $this->actingAs($this->adminUser)->get(
            route('smart.dashboard', [
                'tab' => 'stock_movement',
                'year' => 2026,
                'month' => 2,
            ]),
            [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version,
                'X-Inertia-Partial-Data' => 'stockMovementData',
                'X-Inertia-Partial-Component' => 'Smart/MultiRoles/Dashboard',
            ]
        );

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('component', $json);
        $this->assertEquals('Smart/MultiRoles/Dashboard', $json['component']);
        $this->assertArrayHasKey('props', $json);
        $this->assertArrayHasKey('stockMovementData', $json['props']);
        $this->assertEquals(50, $json['props']['stockMovementData']['summary']['total_in']);
        $this->assertEquals(10, $json['props']['stockMovementData']['summary']['total_out']);
        $this->assertEquals(40, $json['props']['stockMovementData']['summary']['net_movement']);
        $this->assertEquals(2, $json['props']['stockMovementData']['summary']['total_events']);
        $this->assertArrayHasKey('ranking', $json['props']['stockMovementData']);
        $this->assertCount(2, $json['props']['stockMovementData']['logs']);
    }

    public function test_admin_receives_stock_movement_data_directly_when_on_stock_movement_tab(): void
    {
        $response = $this->actingAs($this->adminUser)->get(
            route('smart.dashboard', ['tab' => 'stock_movement', 'year' => 2026, 'month' => 2])
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/MultiRoles/Dashboard')
            ->has('stockMovementData.summary')
            ->where('stockMovementData.summary.total_in', 50)
            ->where('stockMovementData.summary.total_out', 10)
            ->has('stockMovementData.ranking')
            ->has('stockMovementData.logs', 2)
        );
    }

    public function test_admin_receives_stock_movement_data_when_year_is_empty_or_decade_selected(): void
    {
        $currentDecade = (string) (floor(now()->year / 10) * 10);

        $response = $this->actingAs($this->adminUser)->get(
            route('smart.dashboard', ['tab' => 'stock_movement', 'decade' => $currentDecade])
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/MultiRoles/Dashboard')
            ->has('stockMovementData.summary')
            ->where('stockMovementData.summary.total_in', 50)
            ->where('stockMovementData.summary.total_out', 10)
            ->where('stockMovementData.filters.year', '')
            ->where('stockMovementData.filters.decade', $currentDecade)
        );
    }

    public function test_consumable_model_detail_shows_inventory_logs_without_error(): void
    {
        $response = $this->actingAs($this->adminUser)->get(
            "/smart/stock-management/{$this->model->id}?tab=lots"
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Admin/StockManagement/ModelDetail')
            ->has('inventoryLogs')
            ->where('inventoryLogs.0.is_consumable', true)
        );
    }

    public function test_non_admin_forbidden_on_dashboard(): void
    {
        config(['app.disable_test_admin_bypass' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('smart.dashboard'));
        $response->assertForbidden();
    }

    protected function tearDown(): void
    {
        config(['app.disable_test_admin_bypass' => false]);
        parent::tearDown();
    }
}
