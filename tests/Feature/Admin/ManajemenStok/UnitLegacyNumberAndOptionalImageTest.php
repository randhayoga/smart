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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnitLegacyNumberAndOptionalImageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Subcategory $subcategory;
    private Brand $brand;
    private Uom $uom;
    private Barang $barang;
    private Organizer $organizer;
    private Location $location;
    private Lot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();

        $category = Category::factory()->create(['name' => 'Elektronik']);
        $this->subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'EL01',
            'is_consumable' => false,
        ]);
        $this->brand = Brand::factory()->create(['name' => 'Lenovo']);
        $this->uom = Uom::factory()->create(['name' => 'Unit']);

        $this->barang = Barang::factory()->create([
            'number' => 'EL01-0001',
            'subcategory_id' => $this->subcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'image_url' => null,
        ]);
        $this->organizer = Organizer::factory()->create(['name' => 'DIV-IT']);
        $this->location = Location::factory()->create();

        $this->lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'image_url' => null,
            'unit_price' => 2000000,
        ]);
    }

    public function test_barang_can_be_created_without_image(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.barangs.store'), [
            'number' => 'EL01-9999',
            'subcategory_id' => $this->subcategory->id,
            'brand_id' => $this->brand->id,
            'uom_id' => $this->uom->id,
            'name' => 'Laptop Tanpa Gambar',
            'specification' => 'Intel i5, 16GB RAM',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('barangs', [
            'number' => 'EL01-9999',
            'name' => 'Laptop Tanpa Gambar',
            'image_url' => null,
        ]);
    }

    public function test_lot_can_be_created_without_image(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-0001-26-IT-0001',
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'po_number' => 'PO-TEST-001',
            'date_of_receipt' => '2026-10-01',
            'unit_price' => 1500000,
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lots', [
            'number' => 'LOT-0001-26-IT-0001',
            'image_url' => null,
        ]);
    }

    public function test_unit_single_can_be_created_without_image(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 2000000,
            'use_lot_image' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('units', [
            'lot_id' => $this->lot->id,
            'status' => 'Tersedia',
            'image_url' => null,
            'legacy_number' => null,
        ]);
    }

    public function test_unit_bulk_can_be_created_without_image(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.bulk-store'), [
            'number' => '00001-EL01-IT-PTRE-26',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 2000000,
            'bulk_quantity' => 3,
            'use_lot_image' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('units', 3);

        $units = Unit::where('lot_id', $this->lot->id)->get();
        foreach ($units as $unit) {
            $this->assertNull($unit->image_url);
            $this->assertNull($unit->legacy_number);
        }
    }

    public function test_legacy_number_can_be_set_and_is_not_unique(): void
    {
        $unit1 = Unit::factory()->create([
            'lot_id' => $this->lot->id,
            'number' => '00001-EL01-IT-PTRE-26',
            'legacy_number' => 'LEGACY-NON-UNIQUE-01',
            'location_id' => $this->location->id,
            'image_url' => null,
        ]);

        $unit2 = Unit::factory()->create([
            'lot_id' => $this->lot->id,
            'number' => '00002-EL01-IT-PTRE-26',
            'legacy_number' => 'LEGACY-NON-UNIQUE-01', // same legacy number allowed
            'location_id' => $this->location->id,
            'image_url' => null,
        ]);

        $this->assertEquals($unit1->legacy_number, $unit2->legacy_number);
        $this->assertDatabaseHas('units', ['id' => $unit1->id, 'legacy_number' => 'LEGACY-NON-UNIQUE-01']);
        $this->assertDatabaseHas('units', ['id' => $unit2->id, 'legacy_number' => 'LEGACY-NON-UNIQUE-01']);
    }

    public function test_unit_update_does_not_require_photo_and_preserves_legacy_number(): void
    {
        $unit = Unit::factory()->create([
            'lot_id' => $this->lot->id,
            'number' => '00001-EL01-IT-PTRE-26',
            'legacy_number' => 'LEGACY-KEEP-ME',
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'image_url' => null,
        ]);

        $response = $this->actingAs($this->user)->put(route('smart.inventory.units.update', $unit), [
            'number' => $unit->number,
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Standby',
            'condition' => 'Bagus',
            'type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 1800000,
            // no image_url sent
        ]);

        $response->assertRedirect();

        $unit->refresh();
        $this->assertEquals('Standby', $unit->status);
        $this->assertEquals('ST', $unit->type);
        $this->assertEquals('LEGACY-KEEP-ME', $unit->legacy_number);
        $this->assertNull($unit->image_url);
    }

    public function test_manajemen_stok_and_lot_show_endpoints_expose_legacy_number(): void
    {
        $unit = Unit::factory()->create([
            'lot_id' => $this->lot->id,
            'number' => '00001-EL01-IT-PTRE-26',
            'legacy_number' => 'LEGACY-EXPOSE-123',
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'image_url' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('smart.inventory.assets'));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarAset')
            ->has('units', fn (Assert $page) => $page
                ->where('0.id', $unit->id)
                ->where('0.legacy_number', 'LEGACY-EXPOSE-123')
                ->etc()
            )
        );

        $lotResponse = $this->actingAs($this->user)->get(route('smart.inventory.lots.show', $this->lot));
        $lotResponse->assertOk();
        $lotResponse->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DetailLOTNonConsumables')
            ->has('units', fn (Assert $page) => $page
                ->where('0.id', $unit->id)
                ->where('0.legacy_number', 'LEGACY-EXPOSE-123')
                ->etc()
            )
        );
    }

    public function test_media_controller_serves_placeholder_image(): void
    {
        Storage::disk('local')->put('inventory/placeholder.jpg', 'fake-image-bytes');

        $response = $this->actingAs($this->user)->get('/media/inventory/placeholder.jpg');
        $response->assertOk();
    }
}
