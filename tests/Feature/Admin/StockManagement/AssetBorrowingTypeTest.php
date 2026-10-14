<?php

namespace Tests\Feature\Admin\StockManagement;

use App\Models\Inventory\Model;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Asset;
use App\Models\Master\Category;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Type;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetBorrowingTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Lot $lot;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->user = User::factory()->create();

        $category = Category::factory()->create(['name' => 'Perangkat Kantor']);
        $type = Type::factory()->create(['category_id' => $category->id, 'code' => 'PK01', 'is_consumable' => false]);
        $model = Model::factory()->create(['type_id' => $type->id]);
        $organizer = Organizer::factory()->create(['name' => 'DIV-IT']);

        $imagePath = Storage::disk('local')->putFile('inventory', UploadedFile::fake()->image('lot.jpg'));
        $this->lot = Lot::factory()->create([
            'model_id' => $model->id,
            'organizer_id' => $organizer->id,
            'image_url' => $imagePath,
            'unit_price' => 7500000,
        ]);
        $this->location = Location::factory()->create();
    }

    public function test_can_create_asset_single_with_borrowing_type_and_classification(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.assets.store'), [
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 7500000,
            'use_lot_image' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('assets', [
            'lot_id' => $this->lot->id,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
        ]);
    }

    public function test_can_create_asset_bulk_with_borrowing_type_and_classification(): void
    {
        $response = $this->actingAs($this->user)->post(route('smart.inventory.assets.bulk-store'), [
            'number' => '00001-PK01-IT-PTRE-26',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 3000000,
            'use_lot_image' => '1',
            'bulk_quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $assets = Asset::where('lot_id', $this->lot->id)->get();
        $this->assertCount(2, $assets);
        foreach ($assets as $asset) {
            $this->assertEquals('ST', $asset->borrowing_type);
            $this->assertEquals('Inventaris', $asset->classification);
        }
    }

    public function test_can_update_asset_single_borrowing_type_and_classification(): void
    {
        $asset = Asset::create([
            'number' => 'UNT-001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $response = $this->actingAs($this->user)->put(route('smart.inventory.assets.update', $asset->id), [
            'number' => 'UNT-001',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Standby',
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 1200000,
            'use_lot_image' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
            'status' => 'Standby',
        ]);
    }

    public function test_bulk_update_assets_preserves_original_values_when_empty(): void
    {
        $asset1 = Asset::create([
            'number' => 'UNT-BULK-01',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'price' => 1000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $asset2 = Asset::create([
            'number' => 'UNT-BULK-02',
            'lot_id' => $this->lot->id,
            'location_id' => $this->location->id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
            'price' => 2000000,
            'image_url' => 'inventory/placeholder.jpg',
        ]);

        $response = $this->actingAs($this->user)->post(route('smart.inventory.assets.bulk-update'), [
            'ids' => [$asset1->id, $asset2->id],
            'condition' => 'Rusak',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('assets', [
            'id' => $asset1->id,
            'borrowing_type' => 'LT',
            'classification' => 'Aset',
            'condition' => 'Rusak',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset2->id,
            'borrowing_type' => 'ST',
            'classification' => 'Inventaris',
            'condition' => 'Rusak',
        ]);
    }
}
