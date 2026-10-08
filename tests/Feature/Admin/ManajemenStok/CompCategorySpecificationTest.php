<?php

namespace Tests\Feature\Admin\ManajemenStok;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompCategorySpecificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $compCategory;
    private Category $otherCategory;
    private Subcategory $compSubcategory;
    private Subcategory $otherSubcategory;
    private Brand $brand;
    private Uom $uom;
    private Organizer $organizer;
    private Location $location;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->compCategory = Category::factory()->create([
            'code' => 'COMP',
            'name' => 'Computer',
        ]);
        $this->compSubcategory = Subcategory::factory()->create([
            'category_id' => $this->compCategory->id,
            'code' => 'COMP-NB',
            'name' => 'Notebook',
            'is_consumable' => false,
        ]);

        $this->otherCategory = Category::factory()->create([
            'code' => 'ELEC',
            'name' => 'Elektronik',
        ]);
        $this->otherSubcategory = Subcategory::factory()->create([
            'category_id' => $this->otherCategory->id,
            'code' => 'ELEC-TV',
            'name' => 'Televisi',
            'is_consumable' => false,
        ]);

        $this->brand = Brand::factory()->create(['name' => 'Dell']);
        $this->uom = Uom::factory()->create(['name' => 'Unit']);
        $this->organizer = Organizer::factory()->create(['name' => 'DIV-IT']);
        $this->location = Location::factory()->create();
        $this->vendor = Vendor::factory()->create(['name' => 'PT Mitra Solusi']);
    }

    public function test_comp_category_barang_forces_specification_null_on_creation(): void
    {
        $response = $this->actingAs($this->user)->post('/smart/inventory/barangs', [
            'number' => 'COMP-NB-0001',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Dell Latitude 5420',
            'specification' => 'Intel Core i7, 16GB RAM',
        ]);

        $response->assertSessionHasNoErrors();
        $barang = Barang::where('number', 'COMP-NB-0001')->firstOrFail();
        $this->assertNull($barang->specification);
    }

    public function test_comp_category_barang_forces_specification_null_on_update(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'COMP-NB-0002',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Dell Latitude 7420',
            'specification' => null,
        ]);

        $response = $this->actingAs($this->user)->put("/smart/inventory/barangs/{$barang->id}", [
            'number' => 'COMP-NB-0002',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Dell Latitude 7420 Updated',
            'specification' => 'Should be ignored and set to null',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNull($barang->fresh()->specification);
    }

    public function test_non_comp_category_barang_preserves_specification(): void
    {
        $response = $this->actingAs($this->user)->post('/smart/inventory/barangs', [
            'number' => 'ELEC-TV-0001',
            'subcategory_id' => $this->otherSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Samsung Smart TV 55',
            'specification' => '4K UHD, HDR10+, Smart Hub',
        ]);

        $response->assertSessionHasNoErrors();
        $barang = Barang::where('number', 'ELEC-TV-0001')->firstOrFail();
        $this->assertSame('4K UHD, HDR10+, Smart Hub', $barang->specification);
    }

    public function test_identifiers_support_up_to_50_characters(): void
    {
        $longBarangNumber = 'COMP-NB-EXTREMELY-LONG-NUMBER-IDENTIFIER-50-CHARS!';
        $this->assertSame(50, strlen($longBarangNumber));

        $barang = Barang::factory()->create([
            'number' => $longBarangNumber,
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
        ]);
        $this->assertSame($longBarangNumber, $barang->fresh()->number);

        $longLotNumber = 'LOT-COMP-NB-EXTREMELY-LONG-NUMBER-IDENTIFIER-50-CH';
        $this->assertSame(50, strlen($longLotNumber));

        $lot = Lot::factory()->create([
            'number' => $longLotNumber,
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
        ]);
        $this->assertSame($longLotNumber, $lot->fresh()->number);

        $longUnitNumber = 'UNIT-COMP-NB-EXTREMELY-LONG-NUMBER-IDENTIFIER-50-C';
        $this->assertSame(50, strlen($longUnitNumber));

        $unit = Unit::factory()->create([
            'number' => $longUnitNumber,
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'type' => 'LT',
            'classification' => 'Aset',
        ]);
        $this->assertSame($longUnitNumber, $unit->fresh()->number);
    }

    public function test_lot_vendor_id_is_not_nullable(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'COMP-NB-0003',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-NON-NULL-VENDOR-01',
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertSame($this->vendor->id, $lot->fresh()->vendor_id);
    }

    public function test_unit_vendor_id_supports_legacy_migration_vendor(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'COMP-NB-0004',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-0004',
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
        ]);

        $unitWithVendor = Unit::factory()->create([
            'number' => 'UNIT-WITH-VENDOR-01',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'LT',
            'classification' => 'Aset',
        ]);

        $unitWithoutVendor = Unit::factory()->create([
            'number' => 'UNIT-NO-VENDOR-01',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'vendor_id' => null,
            'type' => 'LT',
            'classification' => 'Aset',
        ]);

        $this->assertSame($this->vendor->id, $unitWithVendor->fresh()->vendor_id);
        $this->assertSame('PT Mitra Solusi', $unitWithVendor->fresh()->vendor_name);
        $this->assertInstanceOf(Vendor::class, $unitWithVendor->fresh()->vendor);

        $this->assertNull($unitWithoutVendor->fresh()->vendor_id);
        $this->assertNull($unitWithoutVendor->fresh()->vendor_name);
        $this->assertNull($unitWithoutVendor->fresh()->vendor);
    }

    public function test_comp_category_unit_stores_and_updates_specification(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'COMP-NB-0005',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'specification' => null,
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-0005',
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/inventory/units', [
            'number' => 'UNIT-COMP-SPEC-01',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'specification' => 'Core i7-1185G7, 32GB RAM, 512GB SSD',
        ]);

        $response->assertSessionHasNoErrors();
        $unit = Unit::where('lot_id', $lot->id)->firstOrFail();
        $this->assertSame('Core i7-1185G7, 32GB RAM, 512GB SSD', $unit->specification);

        // Update specification
        $updateResponse = $this->actingAs($this->user)->put("/smart/inventory/units/{$unit->id}", [
            'number' => $unit->number,
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'specification' => 'Core i7-1185G7, 64GB RAM, 1TB SSD',
        ]);

        $updateResponse->assertSessionHasNoErrors();
        $this->assertSame('Core i7-1185G7, 64GB RAM, 1TB SSD', $unit->fresh()->specification);
    }

    public function test_non_comp_category_unit_forces_specification_null(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'ELEC-TV-0002',
            'subcategory_id' => $this->otherSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'specification' => '4K UHD, HDR10+',
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-ELEC-01',
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $this->vendor->id,
            'location_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/inventory/units', [
            'number' => 'UNIT-ELEC-01',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'specification' => 'Unit spec that should be null for non-COMP',
        ]);

        $response->assertSessionHasNoErrors();
        $unit = Unit::where('lot_id', $lot->id)->firstOrFail();
        $this->assertNull($unit->specification);
    }

    public function test_bulk_unit_creation_stores_specification_for_comp_units(): void
    {
        $barang = Barang::factory()->create([
            'number' => 'COMP-NB-0006',
            'subcategory_id' => $this->compSubcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
        ]);

        $lot = Lot::factory()->create([
            'number' => 'LOT-0006',
            'barang_id' => $barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)->post('/smart/inventory/units/bulk', [
            'number' => '00001-COMP-NB-DIV-IT-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $this->location->id,
            'bulk_quantity' => 2,
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'burden' => 'Corporate',
            'specification' => 'Core i5-1135G7, 8GB RAM, 256GB SSD',
        ]);

        $response->assertSessionHasNoErrors();
        $units = Unit::where('lot_id', $lot->id)->get();
        $this->assertCount(2, $units);
        foreach ($units as $unit) {
            $this->assertSame('Core i5-1135G7, 8GB RAM, 256GB SSD', $unit->specification);
        }
    }
}
