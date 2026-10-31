<?php

namespace Tests\Feature\Admin\ManualRequest;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRequestCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['active' => 1]);
        $this->category = Category::factory()->create(['code' => 'IT', 'name' => 'IT Equipment']);
        $this->brand = Brand::factory()->create(['name' => 'Dell']);
        $this->uom = Uom::factory()->create(['name' => 'Unit']);
        $this->organizer = Organizer::factory()->create(['name' => 'IT']);
        $this->location = Location::factory()->create(['name' => 'Room 101']);
    }

    public function test_returns_only_in_stock_consumable_models_and_filters_by_search(): void
    {
        $consumableType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'BAT',
            'name' => 'Battery',
            'is_consumable' => true,
        ]);

        $inStockModel = InventoryModel::factory()->create([
            'type_id' => $consumableType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Alkaline AA',
            'specification' => '1.5V Pack',
        ]);

        $outOfStockModel = InventoryModel::factory()->create([
            'type_id' => $consumableType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Alkaline AAA',
            'specification' => '1.5V Empty',
        ]);

        Lot::factory()->create([
            'model_id' => $inStockModel->id,
            'organizer_id' => $this->organizer->id,
            'current_quantity' => 15,
            'initial_quantity' => 15,
        ]);

        Lot::factory()->create([
            'model_id' => $outOfStockModel->id,
            'organizer_id' => $this->organizer->id,
            'current_quantity' => 0,
            'initial_quantity' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-catalog', ['type' => 'consumable', 'search' => 'Alkaline']));

        $response->assertOk();
        $items = $response->json('items');
        $models = $response->json('models');
        $this->assertCount(1, $items);
        $this->assertCount(1, $models);
        $this->assertEquals($inStockModel->id, $items[0]['id']);
        $this->assertEquals($inStockModel->number, $items[0]['number']);
        $this->assertEquals('Dell', $items[0]['brand_name']);
        $this->assertEquals('Unit', $items[0]['uom_name']);
        $this->assertEquals(15, $items[0]['available_stock']);
    }

    public function test_returns_only_available_st_or_lt_assets_with_null_approval_status(): void
    {
        $assetType = Type::factory()->create([
            'category_id' => $this->category->id,
            'code' => 'LAP',
            'name' => 'Laptop',
            'is_consumable' => false,
        ]);

        $model = InventoryModel::factory()->create([
            'type_id' => $assetType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Latitude 5440',
            'specification' => 'i7 16GB',
        ]);

        $lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $this->organizer->id,
        ]);

        $stAvailable = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-ST-100',
            'status' => 'Tersedia',
            'approval_status' => null,
            'borrowing_type' => 'ST',
        ]);

        // Pending approval ST asset (must be excluded)
        Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-ST-101',
            'status' => 'Tersedia',
            'approval_status' => 'Pending:DM',
            'borrowing_type' => 'ST',
        ]);

        // Borrowed ST asset (must be excluded)
        Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-ST-102',
            'status' => 'Dipinjam (ST)',
            'approval_status' => null,
            'borrowing_type' => 'ST',
        ]);

        $ltAvailable = Asset::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'number' => 'AST-LT-200',
            'status' => 'Tersedia',
            'approval_status' => null,
            'borrowing_type' => 'LT',
        ]);

        $stResponse = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-catalog', ['type' => 'st']));
        $stResponse->assertOk();
        $this->assertCount(1, $stResponse->json('items'));
        $this->assertCount(1, $stResponse->json('assets'));
        $this->assertEquals($stAvailable->id, $stResponse->json('items.0.id'));
        $this->assertEquals('AST-ST-100', $stResponse->json('items.0.number'));
        $this->assertEquals('Dell', $stResponse->json('items.0.brand_name'));
        $this->assertEquals('Unit', $stResponse->json('items.0.uom_name'));
        $this->assertEquals('Room 101', $stResponse->json('items.0.location_name'));

        $ltResponse = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-catalog', ['type' => 'lt']));
        $ltResponse->assertOk();
        $this->assertCount(1, $ltResponse->json('items'));
        $this->assertCount(1, $ltResponse->json('assets'));
        $this->assertEquals($ltAvailable->id, $ltResponse->json('items.0.id'));

        $rentResponse = $this->actingAs($this->admin)
            ->getJson(route('smart.requests.manual-catalog', ['type' => 'rent']));
        $rentResponse->assertOk();
        $this->assertCount(2, $rentResponse->json('items'));
        $this->assertCount(2, $rentResponse->json('assets'));
        $rentIds = array_column($rentResponse->json('items'), 'id');
        $this->assertContains($stAvailable->id, $rentIds);
        $this->assertContains($ltAvailable->id, $rentIds);
    }
}
