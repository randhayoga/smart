<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Master\Category;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Type;
use App\Models\Master\Brand;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Uom;
use App\Models\Auth\Role;
use App\Models\Auth\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewStockControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $compCategory;
    private Category $kendCategory;
    private Category $otherCategory;
    private Type $compType;
    private Type $kendType;
    private Type $consumableType;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator']);
        $permissions = [
            'inventory.view',
            'inventory.manage',
        ];
        foreach ($permissions as $permName) {
            $perm = Permission::firstOrCreate(['name' => $permName], ['label' => $permName, 'group' => 'inventory']);
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $this->admin->assignRole($adminRole);

        $this->compCategory = Category::factory()->create([
            'code' => 'COMP',
            'name' => 'Computer',
        ]);
        $this->compType = Type::factory()->create([
            'category_id' => $this->compCategory->id,
            'code' => 'COMP-NB',
            'name' => 'Notebook',
            'is_consumable' => false,
        ]);

        $this->kendCategory = Category::factory()->create([
            'code' => 'KEND',
            'name' => 'Kendaraan',
        ]);
        $this->kendType = Type::factory()->create([
            'category_id' => $this->kendCategory->id,
            'code' => 'KEND-MOB',
            'name' => 'Mobil',
            'is_consumable' => false,
        ]);

        $this->otherCategory = Category::factory()->create([
            'code' => 'ATK',
            'name' => 'Alat Tulis Kantor',
        ]);
        $this->consumableType = Type::factory()->create([
            'category_id' => $this->otherCategory->id,
            'code' => 'ATK-KTS',
            'name' => 'Kertas',
            'is_consumable' => true,
        ]);

        $this->brand = Brand::factory()->create(['name' => 'HP']);
        $this->uom = Uom::factory()->create(['name' => 'Unit']);
        $this->organizer = Organizer::factory()->create(['name' => 'CFS']);
        $this->location = Location::factory()->create();
    }

    public function test_can_create_model_only(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->otherCategory->id,
            'type_id' => $this->consumableType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Kertas A4 80gr',
            'model_specification' => 'Rim 500 lembar',
            'uom_id' => $this->uom->id,
            'min_stock_threshold' => 10,
            'input_lot' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('models', [
            'name' => 'Kertas A4 80gr',
            'type_id' => $this->consumableType->id,
            'brand_id' => $this->brand->id,
            'min_stock_threshold' => 10,
            'specification' => 'Rim 500 lembar',
        ]);
    }

    public function test_comp_category_model_specification_is_forced_null(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'HP EliteBook 840',
            'model_specification' => 'This should be null on model',
            'uom_id' => $this->uom->id,
            'input_lot' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $model = InventoryModel::where('name', 'HP EliteBook 840')->firstOrFail();
        $this->assertNull($model->specification);
    }

    public function test_can_create_new_procurement_lot_for_existing_model(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'number' => 'COMP-NB-0001',
            'name' => 'Existing Laptop',
        ]);

        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => false,
            'model_id' => $model->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-2026-99',
            'purchase_date' => '2026-10-14',
            'input_asset' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lots', [
            'model_id' => $model->id,
            'entry_type' => 'Procurement',
            'po_number' => 'PO-2026-99',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
        ]);
    }

    public function test_can_create_new_hibah_lot_with_memo_file(): void
    {
        $model = InventoryModel::factory()->create([
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'number' => 'COMP-NB-0002',
            'name' => 'Donated Laptop',
        ]);

        $file = UploadedFile::fake()->create('grant_memo.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => false,
            'model_id' => $model->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Hibah',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'memo_number' => 'MEMO-GRANT-01',
            'date_of_grant' => '2026-10-14',
            'memo_file' => $file,
            'input_asset' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $lot = Lot::where('memo_number', 'MEMO-GRANT-01')->firstOrFail();
        $this->assertNotNull($lot->memo_url);
        Storage::disk('local')->assertExists($lot->memo_url);
    }

    public function test_can_create_model_lot_and_asset_atomically(): void
    {
        $photo = UploadedFile::fake()->image('laptop.png');

        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Atomic Laptop Pro',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-ATOMIC-01',
            'purchase_date' => '2026-10-14',
            'input_asset' => true,
            'asset_specification' => 'Intel Core Ultra 7, 32GB RAM, 1TB SSD',
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'price' => 15000000,
            'classification' => 'Aset',
            'photo' => $photo,
        ]);

        $response->assertSessionHasNoErrors();

        $model = InventoryModel::where('name', 'Atomic Laptop Pro')->firstOrFail();
        $this->assertNull($model->specification);

        $lot = Lot::where('model_id', $model->id)->firstOrFail();
        $this->assertEquals('PO-ATOMIC-01', $lot->po_number);

        $asset = Asset::where('lot_id', $lot->id)->firstOrFail();
        $this->assertEquals('Intel Core Ultra 7, 32GB RAM, 1TB SSD', $asset->comp_specification);
        $this->assertEquals('LT', $asset->borrowing_type);
        $this->assertEquals('Bagus', $asset->condition);
        $this->assertEquals('Belum Diverifikasi', $asset->status);
        $this->assertEquals('Aset', $asset->classification);
        $this->assertEquals(15000000, $asset->price);
        $this->assertNotNull($asset->image_url);
        Storage::disk('local')->assertExists($asset->image_url);

        $this->assertDatabaseHas('asset_activation_approvals', [
            'asset_id' => $asset->id,
            'requester_id' => $this->admin->id,
            'decision' => 'pending',
        ]);
    }

    public function test_kendaraan_asset_requires_plate_number(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->kendCategory->id,
            'type_id' => $this->kendType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Toyota Avanza 1.5G',
            'model_specification' => 'Manual 2026',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-MOB-01',
            'purchase_date' => '2026-10-14',
            'input_asset' => true,
            'borrowing_type' => 'ST',
            'condition' => 'Good',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            // plate_number omitted
        ]);

        $response->assertSessionHasErrors(['plate_number']);
    }

    public function test_kendaraan_asset_saves_plate_number_in_vehicle_registration(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->kendCategory->id,
            'type_id' => $this->kendType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Toyota Avanza 1.5G',
            'model_specification' => 'Manual 2026',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-MOB-01',
            'purchase_date' => '2026-10-14',
            'input_asset' => true,
            'borrowing_type' => 'ST',
            'condition' => 'Good',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'plate_number' => 'B 9999 XYZ',
        ]);

        $response->assertSessionHasNoErrors();
        $asset = Asset::where('vehicle_registration', 'B 9999 XYZ')->firstOrFail();
        $this->assertEquals('Bagus', $asset->condition);
    }
}
