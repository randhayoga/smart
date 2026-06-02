<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\Master\Location;
use App\Models\Master\Floor;
use App\Models\Master\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LotControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_lot(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\Master\Category::factory()->create(['is_consumable' => false]);
        $subcategory = \App\Models\Master\Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();
        $floor = Floor::factory()->create(['location_id' => $location->id]);
        $room = Room::factory()->create(['floor_id' => $floor->id]);

        $file = UploadedFile::fake()->image('lot.jpg');

        $response = $this->actingAs($user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-2026-ATK-KER-0001-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'floor_id' => $floor->id,
            'room_id' => $room->id,
            'po_number' => 'PO-01',
            'date_of_receipt' => '2026-05-22',
            'unit_price' => 60000,
            'image_url' => $file,
            'total_item' => 3,
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('lots', [
            'number' => 'LOT-2026-ATK-KER-0001-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'floor_id' => $floor->id,
            'room_id' => $room->id,
            'po_number' => 'PO-01',
            'unit_price' => 60000,
        ]);

        $lot = Lot::first();
        $this->assertNotNull($lot->image_url);
        $this->assertNotEquals('inventory/lots/placeholder.jpg', $lot->image_url);
        Storage::disk('public')->assertExists($lot->image_url);

        $this->assertEquals(3, $lot->units()->count());
        $this->assertDatabaseHas('units', [
            'lot_id' => $lot->id,
            'number' => 'LOT-2026-ATK-KER-0001-0001-U01',
            'status' => 'tersedia',
            'price' => 60000,
        ]);
    }

    public function test_can_update_lot(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        $lot = Lot::factory()->create();
        
        $newOrganizer = Organizer::factory()->create();
        $newVendor = Vendor::factory()->create();
        $newLocation = Location::factory()->create();
        $newFloor = Floor::factory()->create(['location_id' => $newLocation->id]);
        $newRoom = Room::factory()->create(['floor_id' => $newFloor->id]);

        $response = $this->actingAs($user)->put(route('smart.inventory.lots.update', $lot), [
            'number' => 'LOT-UPDATED',
            'barang_id' => $lot->barang_id,
            'organizer_id' => $newOrganizer->id,
            'vendor_id' => $newVendor->id,
            'location_id' => $newLocation->id,
            'floor_id' => $newFloor->id,
            'room_id' => $newRoom->id,
            'po_number' => 'PO-UPDATED',
            'date_of_receipt' => '2026-05-23',
            'unit_price' => 75000,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('lots', [
            'id' => $lot->id,
            'number' => 'LOT-UPDATED',
            'organizer_id' => $newOrganizer->id,
            'vendor_id' => $newVendor->id,
            'location_id' => $newLocation->id,
            'floor_id' => $newFloor->id,
            'room_id' => $newRoom->id,
            'po_number' => 'PO-UPDATED',
            'unit_price' => 75000,
        ]);
    }

    public function test_can_destroy_lot(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $lot = Lot::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.lots.destroy', $lot));

        $response->assertRedirect();
        $this->assertDatabaseMissing('lots', [
            'id' => $lot->id,
        ]);
    }

    public function test_can_store_lot_using_parent_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        
        $barangImage = UploadedFile::fake()->image('barang.jpg');
        $barangImagePath = Storage::disk('public')->putFile('inventory/barangs', $barangImage);
        
        $category = \App\Models\Master\Category::factory()->create(['is_consumable' => false]);
        $subcategory = \App\Models\Master\Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'image_url' => $barangImagePath,
            'subcategory_id' => $subcategory->id
        ]);
        
        $organizer = Organizer::factory()->create();
        $vendor = Vendor::factory()->create();
        $location = Location::factory()->create();

        $response = $this->actingAs($user)->post(route('smart.inventory.lots.store'), [
            'number' => 'LOT-2026-ATK-KER-0001-0001',
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'vendor_id' => $vendor->id,
            'location_id' => $location->id,
            'po_number' => 'PO-01',
            'date_of_receipt' => '2026-05-22',
            'unit_price' => 60000,
            'use_parent_image' => true,
            'total_item' => 2,
        ]);

        $response->assertRedirect();
        
        $lot = Lot::first();
        $this->assertNotNull($lot->image_url);
        $this->assertNotEquals($barangImagePath, $lot->image_url);
        Storage::disk('public')->assertExists($lot->image_url);
        $this->assertEquals(2, $lot->units()->count());
    }

    public function test_can_update_lot_using_parent_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin']);
        
        $barangImage = UploadedFile::fake()->image('barang.jpg');
        $barangImagePath = Storage::disk('public')->putFile('inventory/barangs', $barangImage);
        $barang = Barang::factory()->create(['image_url' => $barangImagePath]);
        
        $lot = Lot::factory()->create(['barang_id' => $barang->id]);
        $oldLotImagePath = $lot->image_url;
        
        $response = $this->actingAs($user)->put(route('smart.inventory.lots.update', $lot), [
            'number' => $lot->number,
            'barang_id' => $barang->id,
            'organizer_id' => $lot->organizer_id,
            'vendor_id' => $lot->vendor_id,
            'location_id' => $lot->location_id,
            'po_number' => $lot->po_number,
            'date_of_receipt' => $lot->date_of_receipt->format('Y-m-d'),
            'unit_price' => $lot->unit_price,
            'use_parent_image' => true,
        ]);

        $response->assertRedirect();
        
        $lot->refresh();
        $this->assertNotNull($lot->image_url);
        $this->assertNotEquals($barangImagePath, $lot->image_url);
        $this->assertNotEquals($oldLotImagePath, $lot->image_url);
        Storage::disk('public')->assertExists($lot->image_url);
    }
}
