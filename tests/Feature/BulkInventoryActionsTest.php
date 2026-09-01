<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\AdmUser as User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bulk Inventory Actions Feature Tests
 *
 * Verifies batch creation, batch updating, and batch deletion across Barang, Lot, and Unit entities.
 */
class BulkInventoryActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_bulk_update_barangs(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barangs = Barang::factory()->count(3)->create();

        $ids = $barangs->pluck('id')->toArray();

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.bulk-update'), [
            'ids' => $ids,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Barang Bulk Updated',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($ids as $id) {
            $this->assertDatabaseHas('barangs', [
                'id' => $id,
                'brand_id' => $brand->id,
                'uom_id' => $uom->id,
                'name' => 'Barang Bulk Updated',
            ]);
        }
    }

    public function test_can_bulk_destroy_barangs(): void
    {
        $user = User::factory()->create();
        $barangs = Barang::factory()->count(2)->create();
        $ids = $barangs->pluck('id')->toArray();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.bulk-destroy'), [
            'ids' => $ids,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('barangs', ['id' => $id]);
        }
    }

    public function test_cannot_bulk_destroy_barangs_with_lots(): void
    {
        $user = User::factory()->create();
        $barangWithLot = Barang::factory()->create();
        Lot::factory()->create(['barang_id' => $barangWithLot->id]);

        $barangEmpty = Barang::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.bulk-destroy'), [
            'ids' => [$barangWithLot->id, $barangEmpty->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('barangs', ['id' => $barangWithLot->id]);
        $this->assertDatabaseMissing('barangs', ['id' => $barangEmpty->id]);
    }

    public function test_can_bulk_update_lots(): void
    {
        $user = User::factory()->create();
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $lots = Lot::factory()->count(2)->create();
        $ids = $lots->pluck('id')->toArray();

        $response = $this->actingAs($user)->put(route('smart.inventory.lots.bulk-update'), [
            'ids' => $ids,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'unit_price' => 250000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($ids as $id) {
            $this->assertDatabaseHas('lots', [
                'id' => $id,
                'organizer_id' => $organizer->id,
                'vendor_id' => $vendor->id,
                'unit_price' => 250000.00,
            ]);
        }
    }

    public function test_can_bulk_destroy_lots(): void
    {
        $user = User::factory()->create();
        $lots = Lot::factory()->count(2)->create();
        $ids = $lots->pluck('id')->toArray();

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.bulk-destroy'), [
            'ids' => $ids,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('lots', ['id' => $id]);
        }
    }

    public function test_cannot_bulk_destroy_lots_with_units(): void
    {
        $user = User::factory()->create();
        $lotWithUnit = Lot::factory()->create();
        $location = Location::factory()->create();

        Unit::create([
            'number' => 'AST-00001',
            'lot_id' => $lotWithUnit->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 100000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $lotEmpty = Lot::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.bulk-destroy'), [
            'ids' => [$lotWithUnit->id, $lotEmpty->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('lots', ['id' => $lotWithUnit->id]);
        $this->assertDatabaseMissing('lots', ['id' => $lotEmpty->id]);
    }

    public function test_can_bulk_store_units(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Elektronik']);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'code' => 'ELKT']);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $organizer = Organizer::factory()->create(['name' => 'IT']);
        
        $imagePath = Storage::disk('local')->putFile('inventory', UploadedFile::fake()->image('lot.jpg'));
        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'image_url' => $imagePath,
        ]);
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.inventory.units.bulk-store'), [
            'number' => '00001-ELKT-IT-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 1500000,
            'use_lot_image' => '1',
            'bulk_quantity' => 3,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(3, Unit::where('lot_id', $lot->id)->count());
    }

    public function test_can_bulk_update_units(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $location1 = Location::factory()->create(['name' => 'Lokasi 1']);
        $location2 = Location::factory()->create(['name' => 'Lokasi 2']);

        $unit1 = Unit::create([
            'number' => 'AST-001',
            'lot_id' => $lot->id,
            'location_id' => $location1->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 100000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $unit2 = Unit::create([
            'number' => 'AST-002',
            'lot_id' => $lot->id,
            'location_id' => $location1->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'price' => 100000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $response = $this->actingAs($user)->post(route('smart.inventory.units.bulk-update'), [
            'ids' => [$unit1->id, $unit2->id],
            'location_id' => $location2->id,
            'condition' => 'Rusak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', ['id' => $unit1->id, 'location_id' => $location2->id, 'condition' => 'Rusak']);
        $this->assertDatabaseHas('units', ['id' => $unit2->id, 'location_id' => $location2->id, 'condition' => 'Rusak']);
    }

    public function test_can_view_consumable_lots_page(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        $response = $this->actingAs($user)->get(route('smart.inventory.stok-habis-pakai'));

        $response->assertStatus(200);
    }
}
