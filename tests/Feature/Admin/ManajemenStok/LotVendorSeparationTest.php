<?php

namespace Tests\Feature\Admin\ManajemenStok;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\Master\Vendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LotVendorSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Barang $barang;
    private Organizer $organizer;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = User::factory()->create();
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'is_consumable' => false]);
        $this->barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $this->organizer = Organizer::factory()->create();
        $this->location = Location::factory()->create();
    }

    public function test_lot_creation_requires_vendor_id(): void
    {
        $response = $this->actingAs($this->admin)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-0001-26-TEST-0001',
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => null,
            'location_id' => $this->location->id,
            'po_number' => 'PO-NEW-01',
            'date_of_receipt' => '2026-10-01',
            'unit_price' => 50000,
            'burden' => 'Corporate',
        ]);

        $response->assertSessionHasErrors(['vendor_id']);
    }

    public function test_lot_can_be_created_with_vendor_id(): void
    {
        $vendor = Vendor::factory()->create();
        $file = UploadedFile::fake()->image('lot.jpg');

        $response = $this->actingAs($this->admin)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-0002-26-TEST-0001',
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $this->location->id,
            'po_number' => 'PO-NEW-02',
            'date_of_receipt' => '2026-10-01',
            'unit_price' => 75000,
            'image_url' => $file,
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lots', [
            'number' => 'LOT-0002-26-TEST-0001',
            'vendor_id' => $vendor->id,
        ]);
    }

    public function test_existing_lot_with_legacy_vendor_preserves_vendor_information(): void
    {
        $legacyVendor = Vendor::factory()->create([
            'code' => 'VN0001',
            'name' => 'PT Mitra Sejati',
        ]);

        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'vendor_id' => $legacyVendor->id,
            'legacy_vendor_id' => $legacyVendor->id,
        ]);

        $this->assertTrue($lot->legacyVendor->is($legacyVendor));
        $this->assertEquals('PT Mitra Sejati', $lot->vendor_name);
        $this->assertTrue($legacyVendor->lots->contains($lot));

        // Attempting to delete legacy vendor should be blocked
        $destroyResponse = $this->actingAs($this->admin)->delete(route('smart.master.vendors.destroy', $legacyVendor));
        $destroyResponse->assertRedirect();
        $destroyResponse->assertSessionHas('error', __('master.vendors.cannot_delete_used'));
        $this->assertDatabaseHas('vendors', ['id' => $legacyVendor->id]);
    }

    public function test_updating_regular_lot_requires_vendor_id(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'PT Vendor Asli']);

        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'vendor_id' => $vendor->id,
            'legacy_vendor_id' => $vendor->id,
            'unit_price' => 100000,
        ]);

        $response = $this->actingAs($this->admin)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => null,
            'location_id' => $this->location->id,
            'po_number' => 'PO-UPDATED',
            'date_of_receipt' => '2026-10-01',
            'unit_price' => 120000,
            'burden' => 'Corporate',
        ]);

        $response->assertSessionHasErrors(['vendor_id']);
    }

    public function test_updating_beragam_lot_locks_vendor_id(): void
    {
        $beragamVendor = Vendor::factory()->create(['name' => 'Beragam']);
        $otherVendor = Vendor::factory()->create(['name' => 'PT Lain']);

        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'vendor_id' => $beragamVendor->id,
            'legacy_vendor_id' => $beragamVendor->id,
            'unit_price' => 100000,
        ]);

        // Attempting to update with another vendor_id should not change vendor_id
        $response = $this->actingAs($this->admin)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'vendor_id' => $otherVendor->id,
            'location_id' => $this->location->id,
            'po_number' => 'PO-UPDATED',
            'date_of_receipt' => '2026-10-01',
            'unit_price' => 120000,
            'burden' => 'Corporate',
        ]);

        $response->assertRedirect();
        $lot->refresh();
        $this->assertEquals(120000, (int)$lot->unit_price);
        $this->assertEquals($beragamVendor->id, $lot->vendor_id);
    }

    public function test_lot_show_json_returns_vendor_name_and_vendor_id(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'PT Sumber Rezeki']);
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'vendor_id' => $vendor->id,
            'legacy_vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('smart.inventory.lots.show', $lot));
        $response->assertOk();
        $response->assertJson([
            'id' => $lot->id,
            'vendor' => 'PT Sumber Rezeki',
            'vendor_id' => $vendor->id,
            'legacy_vendor_id' => $vendor->id,
        ]);
    }

    public function test_lot_show_inertia_passes_vendors_list(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'Vendor Test']);
        $lot = Lot::factory()->create([
            'barang_id' => $this->barang->id,
            'organizer_id' => $this->organizer->id,
            'location_id' => $this->location->id,
            'vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('smart.inventory.lots.show', $lot));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Smart/Admin/ManajemenStok/DetailLOTNonConsumables')
            ->has('vendors')
        );
    }
}
