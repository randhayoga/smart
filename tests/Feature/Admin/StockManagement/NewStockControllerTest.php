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
use App\Models\Master\Vendor;
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
    private Vendor $vendor;
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
        $this->vendor = Vendor::factory()->create(['name' => 'PT Mitra Sejahtera']);
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
        $model = InventoryModel::where('name', 'Kertas A4 80gr')->firstOrFail();
        $modelParam = preg_replace('/[^a-zA-Z0-9]/', '', (string)$model->number) ?: $model->id;
        $response->assertSessionHas('new_stock_created', [
            'target_url' => '/smart/inventory/' . $modelParam,
            'model_id' => $model->id,
            'lot_id' => null,
            'is_consumable' => true,
            'has_lot' => false,
        ]);
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
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-2026-99',
            'purchase_date' => '2026-10-14',
            'input_asset' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $lot = Lot::where('po_number', 'PO-2026-99')->firstOrFail();
        $lotParam = preg_replace('/[^a-zA-Z0-9]/', '', (string)$lot->number) ?: $lot->id;
        $response->assertSessionHas('new_stock_created', [
            'target_url' => '/smart/inventory/lots/' . $lotParam,
            'model_id' => $model->id,
            'lot_id' => $lot->id,
            'is_consumable' => false,
            'has_lot' => true,
        ]);
        $this->assertDatabaseHas('lots', [
            'model_id' => $model->id,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-2026-99',
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
        ]);
    }

    public function test_procurement_lot_requires_vendor_id(): void
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

        $response->assertSessionHasErrors(['vendor_id']);
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
            'vendor_id' => $this->vendor->id,
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
        $this->assertEquals($this->vendor->id, $lot->vendor_id);

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
            'vendor_id' => $this->vendor->id,
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
            'vendor_id' => $this->vendor->id,
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

    public function test_can_create_bulk_assets_atomically_when_is_bulk_is_true(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->compCategory->id,
            'type_id' => $this->compType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Dell Latitude 5540 Bulk',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-BULK-01',
            'purchase_date' => '2026-10-14',
            'input_asset' => true,
            'asset_specification' => 'Core i7, 16GB, 512GB',
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'price' => 12500000,
            'classification' => 'Aset',
            'is_bulk' => true,
            'bulk_quantity' => 3,
        ]);

        $response->assertSessionHasNoErrors();

        $lot = Lot::where('po_number', 'PO-BULK-01')->firstOrFail();
        $assets = Asset::where('lot_id', $lot->id)->get();

        $this->assertCount(3, $assets);
        $this->assertEquals(3, $assets->pluck('number')->unique()->count());

        foreach ($assets as $asset) {
            $this->assertEquals('Belum Diverifikasi', $asset->status);
            $this->assertEquals('Bagus', $asset->condition);
            $this->assertEquals(12500000, $asset->price);
            $this->assertDatabaseHas('asset_activation_approvals', [
                'asset_id' => $asset->id,
                'requester_id' => $this->admin->id,
                'decision' => 'pending',
            ]);
        }
    }

    public function test_kendaraan_category_disallows_bulk_asset_creation(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->kendCategory->id,
            'type_id' => $this->kendType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Toyota Innova Zenix',
            'model_specification' => 'Hybrid 2026',
            'uom_id' => $this->uom->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-MOB-BULK',
            'purchase_date' => '2026-10-14',
            'input_asset' => true,
            'borrowing_type' => 'ST',
            'condition' => 'Good',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'plate_number' => 'B 7777 ABC',
            'is_bulk' => true,
            'bulk_quantity' => 3,
        ]);

        $response->assertSessionHasNoErrors();
        $lot = Lot::where('po_number', 'PO-MOB-BULK')->firstOrFail();
        // For vehicle category, bulk creation is ignored and only 1 asset with plate number is created
        $assets = Asset::where('lot_id', $lot->id)->get();
        $this->assertCount(1, $assets);
        $this->assertCount(1, $assets);
        $this->assertEquals('B 7777 ABC', $assets->first()->vehicle_registration);
    }

    public function test_can_create_consumable_stock_with_lot_initial_quantity_and_lot_photo(): void
    {
        Storage::fake('local');
        $photo = UploadedFile::fake()->image('lot-consumable.jpg', 300, 300);

        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->otherCategory->id,
            'type_id' => $this->consumableType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Kertas F4 70gr',
            'model_specification' => 'Rim',
            'uom_id' => $this->uom->id,
            'min_stock_threshold' => 5,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-KTS-001',
            'purchase_date' => '2026-10-16',
            'initial_quantity' => 120,
            'location_id' => $this->location->id,
            'unit_price' => 55000,
            'lot_photo' => $photo,
            'input_asset' => false,
        ]);

        $response->assertSessionHasNoErrors();

        $lot = Lot::where('po_number', 'PO-KTS-001')->firstOrFail();
        $model = InventoryModel::where('name', 'Kertas F4 70gr')->firstOrFail();
        $modelParam = preg_replace('/[^a-zA-Z0-9]/', '', (string)$model->number) ?: $model->id;
        $response->assertSessionHas('new_stock_created', [
            'target_url' => '/smart/inventory/' . $modelParam . '?tab=lots&lot_id=' . $lot->id,
            'model_id' => $model->id,
            'lot_id' => $lot->id,
            'is_consumable' => true,
            'has_lot' => true,
        ]);
        $this->assertEquals(120, $lot->initial_quantity);
        $this->assertEquals(120, $lot->current_quantity);
        $this->assertEquals($this->location->id, $lot->location_id);
        $this->assertEquals(55000, (float) $lot->unit_price);
        $this->assertNotNull($lot->image_url);
        Storage::disk('local')->assertExists($lot->image_url);
    }

    public function test_consumable_lot_creation_requires_initial_quantity(): void
    {
        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->otherCategory->id,
            'type_id' => $this->consumableType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Spidol Hitam',
            'model_specification' => 'Permanent',
            'uom_id' => $this->uom->id,
            'min_stock_threshold' => 10,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-SPD-001',
            'purchase_date' => '2026-10-16',
            'input_asset' => false,
        ]);

        $response->assertSessionHasErrors(['initial_quantity']);
    }

    public function test_can_upload_webp_image_and_stores_as_webp(): void
    {
        $gd = imagecreatetruecolor(50, 50);
        ob_start();
        imagewebp($gd);
        $webpData = ob_get_clean();
        imagedestroy($gd);

        $photo = UploadedFile::fake()->createWithContent('consumable-item.webp', $webpData);

        $response = $this->actingAs($this->admin)->post('/smart/inventory/new-stock', [
            'category_id' => $this->otherCategory->id,
            'type_id' => $this->consumableType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => true,
            'model_name' => 'Sticky Notes Webp',
            'model_specification' => 'Yellow',
            'uom_id' => $this->uom->id,
            'min_stock_threshold' => 5,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'vendor_id' => $this->vendor->id,
            'organizer_id' => $this->organizer->id,
            'burden' => 'Corporate',
            'po_number' => 'PO-WEBP-001',
            'purchase_date' => '2026-10-19',
            'initial_quantity' => 50,
            'location_id' => $this->location->id,
            'unit_price' => 15000,
            'lot_photo' => $photo,
            'input_asset' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $lot = Lot::where('po_number', 'PO-WEBP-001')->firstOrFail();
        $this->assertNotNull($lot->image_url);
        $this->assertStringEndsWith('.webp', $lot->image_url);
        Storage::disk('local')->assertExists($lot->image_url);
    }
}
