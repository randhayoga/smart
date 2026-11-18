<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Asset;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Model as InventoryModel;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use App\Services\Inventory\StockInventoryQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSerialAndWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Type $type;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Vendor $vendor;
    private Location $location;
    private InventoryModel $model;
    private Lot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        $this->category = Category::firstOrCreate(['name' => 'Electronics'], ['code' => 'ELEC']);
        $this->type = Type::firstOrCreate(['name' => 'Monitor', 'category_id' => $this->category->id], ['code' => 'MON', 'is_consumable' => false]);
        $this->brand = Brand::firstOrCreate(['name' => 'Dell']);
        $this->uom = Uom::firstOrCreate(['name' => 'Unit']);
        $this->organizer = Organizer::firstOrCreate(['name' => 'IT Dept'], ['code' => 'IT']);
        $this->vendor = Vendor::firstOrCreate(['name' => 'Tech Vendor'], ['code' => 'VND']);
        $this->location = Location::firstOrCreate(['name' => 'HQ'], ['full_name' => 'HQ - Floor 1']);

        $this->model = InventoryModel::create([
            'number' => 'MOD-001',
            'type_id' => $this->type->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Dell 27"',
            'specification' => '4K IPS',
        ]);

        $this->lot = Lot::create([
            'number' => 'LOT-001',
            'model_id' => $this->model->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'entry_type' => 'Procurement',
            'po_number' => 'PO-001',
            'purchase_grant_date' => now()->format('Y-m-d'),
            'burden' => 'Corporate',
        ]);
    }

    public function test_single_asset_creation_stores_serial_number_and_warranty_date(): void
    {
        $response = $this->actingAs($this->admin)->post(route('smart.inventory.assets.store'), [
            'number' => 'AST-SN-001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'serial_number' => 'SN-DELL-12345',
            'warranty_date' => '2028-12-31',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $asset = Asset::where('serial_number', 'SN-DELL-12345')->firstOrFail();
        $this->assertSame('SN-DELL-12345', $asset->serial_number);
        $this->assertSame('2028-12-31', $asset->warranty_date?->format('Y-m-d'));
    }

    public function test_bulk_asset_creation_forces_serial_number_null_when_quantity_gt_1_but_keeps_when_1(): void
    {
        // Quantity > 1 => serial_number forced null, warranty_date saved
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.bulk-store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'bulk_quantity' => 2,
            'serial_number' => 'SHOULD-BE-NULL',
            'warranty_date' => '2027-06-15',
        ])->assertRedirect();

        $bulkAssets = Asset::where('lot_id', $this->lot->id)->get();
        $this->assertCount(2, $bulkAssets);
        foreach ($bulkAssets as $a) {
            $this->assertNull($a->serial_number);
            $this->assertSame('2027-06-15', $a->warranty_date?->format('Y-m-d'));
        }

        // Quantity === 1 => serial_number preserved
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.bulk-store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'bulk_quantity' => 1,
            'serial_number' => 'SN-SINGLE-BULK',
            'warranty_date' => '2029-01-01',
        ])->assertRedirect();

        $singleBulk = Asset::where('serial_number', 'SN-SINGLE-BULK')->first();
        $this->assertNotNull($singleBulk);
        $this->assertSame('2029-01-01', $singleBulk->warranty_date?->format('Y-m-d'));
    }

    public function test_new_stock_controller_handles_serial_number_and_warranty_date(): void
    {
        // Single asset via new-stock
        $this->actingAs($this->admin)->post(route('smart.inventory.new-stock.store'), [
            'category_id' => $this->category->id,
            'type_id' => $this->type->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => false,
            'model_id' => $this->model->id,
            'input_lot' => true,
            'create_new_lot' => false,
            'lot_id' => $this->lot->id,
            'input_asset' => true,
            'borrowing_type' => 'LT',
            'condition' => 'Bagus',
            'location_id' => $this->location->id,
            'classification' => 'Aset',
            'is_bulk' => false,
            'is_non_owned' => true,
            'serial_number' => 'SN-NEW-STOCK-1',
            'warranty_date' => '2028-05-20',
        ])->assertRedirect();

        $created = Asset::where('serial_number', 'SN-NEW-STOCK-1')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->is_non_owned);
        $this->assertSame('2028-05-20', $created->warranty_date?->format('Y-m-d'));
    }

    public function test_single_and_mass_update_for_serial_number_and_warranty_date(): void
    {
        $asset1 = Asset::create([
            'number' => 'AST-UPD-1',
            'serial_number' => 'OLD-SN-1',
            'warranty_date' => '2026-01-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
        ]);

        $asset2 = Asset::create([
            'number' => 'AST-UPD-2',
            'serial_number' => 'OLD-SN-2',
            'warranty_date' => '2026-01-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
        ]);

        // Single update changes both serial_number and warranty_date
        $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset1->id), [
            'number' => $asset1->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'serial_number' => 'NEW-SN-1',
            'warranty_date' => '2030-03-15',
        ])->assertRedirect();

        $asset1->refresh();
        $this->assertSame('NEW-SN-1', $asset1->serial_number);
        $this->assertSame('2030-03-15', $asset1->warranty_date?->format('Y-m-d'));

        // Mass update updates warranty_date but ignores serial_number
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.bulk-update'), [
            'ids' => [$asset1->id, $asset2->id],
            'serial_number' => 'MASS-SHOULD-IGNORE',
            'warranty_date' => '2031-11-30',
        ])->assertRedirect();

        $asset1->refresh();
        $asset2->refresh();
        $this->assertSame('NEW-SN-1', $asset1->serial_number);
        $this->assertSame('OLD-SN-2', $asset2->serial_number);
        $this->assertSame('2031-11-30', $asset1->warranty_date?->format('Y-m-d'));
        $this->assertSame('2031-11-30', $asset2->warranty_date?->format('Y-m-d'));
    }

    public function test_detail_scan_and_compact_query_include_serial_number_and_warranty_date(): void
    {
        $asset = Asset::create([
            'number' => 'AST-DET-1',
            'serial_number' => 'SN-DET-999',
            'warranty_date' => '2029-08-10',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'active_status' => 'Aktif',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('smart.inventory.assets.show', $asset->id))
            ->assertOk()
            ->assertJsonPath('serial_number', 'SN-DET-999')
            ->assertJsonPath('warranty_date', '2029-08-10');

        $compact = app(StockInventoryQueryService::class)->getCompactUnitsPayload();
        $this->assertNotEmpty($compact['rows']);
        $row = collect($compact['rows'])->first(fn ($r) => $r[0] === $asset->id);
        $this->assertNotNull($row);
        $sparse = $row[8] ?? [];
        $this->assertSame('SN-DET-999', $sparse['sn'] ?? null);
        $this->assertSame('2029-08-10', $sparse['wd'] ?? null);
    }

    public function test_location_is_optional_on_asset_and_consumable_lot_creation_but_required_on_asset_update(): void
    {
        // 1. Single non-consumable asset creation without location_id succeeds
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.store'), [
            'lot_id' => $this->lot->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'serial_number' => 'SN-NO-LOC-1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $asset = Asset::where('serial_number', 'SN-NO-LOC-1')->firstOrFail();
        $this->assertNull($asset->location_id);

        // 2. Bulk non-consumable asset creation without location_id succeeds
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.bulk-store'), [
            'lot_id' => $this->lot->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
            'bulk_quantity' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();

        // 3. Consumable LOT creation without location_id succeeds
        $consumableType = Type::firstOrCreate(['name' => 'Paper', 'category_id' => $this->category->id], ['code' => 'PPR', 'is_consumable' => true]);
        $consumableModel = InventoryModel::create([
            'number' => 'MOD-CON-001',
            'type_id' => $consumableType->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'A4 Paper',
            'specification' => '80gsm',
            'min_stock_threshold' => 5,
        ]);

        $this->actingAs($this->admin)->post(route('smart.inventory.new-stock.store'), [
            'category_id' => $this->category->id,
            'type_id' => $consumableType->id,
            'brand_id' => $this->brand->id,
            'create_new_type' => false,
            'model_id' => $consumableModel->id,
            'input_lot' => true,
            'create_new_lot' => true,
            'entry_type' => 'Procurement',
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'po_number' => 'PO-CON-001',
            'purchase_date' => now()->format('Y-m-d'),
            'burden' => 'Corporate',
            'initial_quantity' => 10,
            'input_asset' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();

        // 4. Updating a non-software asset without location_id still fails validation
        $this->actingAs($this->admin)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => $asset->number,
            'lot_id' => $this->lot->id,
            'status' => $asset->status,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => false,
            'burden' => 'Corporate',
        ])->assertSessionHasErrors(['location_id']);
    }

    public function test_is_non_owned_is_required_on_create_and_supported_in_detail_and_compact_payload(): void
    {
        // Missing is_non_owned fails validation on asset creation
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.store'), [
            'lot_id' => $this->lot->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
        ])->assertSessionHasErrors(['is_non_owned']);

        // Create managed-only asset
        $this->actingAs($this->admin)->post(route('smart.inventory.assets.store'), [
            'lot_id' => $this->lot->id,
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'is_non_owned' => true,
            'burden' => 'Corporate',
            'serial_number' => 'SN-MANAGED-1',
        ])->assertSessionHasNoErrors();

        $managed = Asset::where('serial_number', 'SN-MANAGED-1')->firstOrFail();
        $this->assertTrue($managed->is_non_owned);

        $this->actingAs($this->admin)
            ->getJson(route('smart.inventory.assets.show', $managed->id))
            ->assertOk()
            ->assertJsonPath('is_non_owned', true);

        $compact = app(StockInventoryQueryService::class)->getCompactUnitsPayload();
        $row = collect($compact['rows'])->first(fn ($r) => $r[0] === $managed->id);
        $this->assertNotNull($row);
        $this->assertSame(1, $row[8]['no'] ?? null);
    }
}
