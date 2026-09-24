<?php

namespace Tests\Feature\Admin\ManajemenStok;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitTypeClassificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();

        $category = Category::factory()->create(['name' => 'Perangkat Kantor', 'is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id, 'code' => 'PK01']);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);
        $organizer = Organizer::factory()->create(['name' => 'DIV-IT']);

        $imagePath = Storage::disk('local')->putFile('inventory', UploadedFile::fake()->image('lot.jpg'));
        $this->lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
            'image_url' => $imagePath,
            'unit_price' => 7500000,
        ]);
        $this->location = Location::factory()->create();
    }

    public function test_can_create_unit_single_with_type_and_classification(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 7500000,
            'use_lot_image' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', [
            'lot_id' => $this->lot->id,
            'type' => 'LT',
            'classification' => 'Aset',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);
    }

    public function test_can_create_unit_bulk_with_type_and_classification(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.bulk-store'), [
            'number' => '00001-PK01-IT-PTRE-26',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 3000000,
            'use_lot_image' => '1',
            'bulk_quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $units = Unit::where('lot_id', $this->lot->id)->get();
        $this->assertCount(2, $units);
        foreach ($units as $unit) {
            $this->assertEquals('ST', $unit->type);
            $this->assertEquals('Inventaris', $unit->classification);
        }
    }

    public function test_can_update_unit_single_type_and_classification(): void
    {
        $unit = Unit::create([
            'number' => 'UNT-001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 1000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $response = $this->actingAs($this->user)->put(route('smart.inventory.units.update', $unit->id), [
            'number' => 'UNT-001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Standby',
            'condition' => 'Bagus',
            'type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 1200000,
            'use_lot_image' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'type' => 'ST',
            'classification' => 'Inventaris',
            'status' => 'Standby',
        ]);
    }

    public function test_bulk_update_units_preserves_original_values_when_empty(): void
    {
        $unit1 = Unit::create([
            'number' => 'UNT-BULK-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 1000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $unit2 = Unit::create([
            'number' => 'UNT-BULK-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 2000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        // Bulk update condition only, leaving type and classification empty
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.bulk-update'), [
            'ids' => [$unit1->id, $unit2->id],
            'condition' => 'Rusak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Unit 1 should retain LT / Aset
        $this->assertDatabaseHas('units', [
            'id' => $unit1->id,
            'type' => 'LT',
            'classification' => 'Aset',
            'condition' => 'Rusak',
        ]);

        // Unit 2 should retain ST / Inventaris
        $this->assertDatabaseHas('units', [
            'id' => $unit2->id,
            'type' => 'ST',
            'classification' => 'Inventaris',
            'condition' => 'Rusak',
        ]);
    }

    public function test_bulk_update_units_updates_type_and_classification_when_provided(): void
    {
        $unit1 = Unit::create([
            'number' => 'UNT-BULK-03',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 1000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $unit2 = Unit::create([
            'number' => 'UNT-BULK-04',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'LT',
            'classification' => 'Aset',
            'price' => 2000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.bulk-update'), [
            'ids' => [$unit1->id, $unit2->id],
            'type' => 'ST',
            'classification' => 'Inventaris',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', [
            'id' => $unit1->id,
            'type' => 'ST',
            'classification' => 'Inventaris',
        ]);

        $this->assertDatabaseHas('units', [
            'id' => $unit2->id,
            'type' => 'ST',
            'classification' => 'Inventaris',
        ]);
    }

    public function test_validation_rejects_invalid_type_or_classification(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.units.store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'type' => 'INVALID',
            'classification' => 'INVALID_CLASSIFICATION',
            'use_lot_image' => '1',
        ]);

        $response->assertSessionHasErrors(['type', 'classification']);
    }
}
