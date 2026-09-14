<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\InventoryLog;
use App\Models\Inventory\Lot;
use App\Models\AdmUser as User;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Barang Controller Feature Tests
 *
 * Verifies single and bulk operations on item definitions (Barang), deletion constraints with LOTs, and authenticated media access.
 */
class BarangControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_destroy_barang_without_lots(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertRedirect(route('smart.inventory'));
        $response->assertSessionHas('success', 'Tipe berhasil dihapus.');
        $this->assertDatabaseMissing('barangs', [
            'id' => $barang->id,
        ]);
    }

    public function test_cannot_destroy_barang_with_lots(): void
    {
        $user = User::factory()->create();
        $lot = Lot::factory()->create();
        $barang = $lot->barang;

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Barang tidak dapat dihapus karena masih memiliki LOT terkait.');
        $this->assertDatabaseHas('barangs', [
            'id' => $barang->id,
        ]);
    }

    public function test_can_update_barang(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create([
            'name' => 'Nama Old',
            'specification' => 'Spec Old',
        ]);

        $newBrand = Brand::factory()->create();
        $newUom = Uom::factory()->create();

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.update', $barang), [
            'number' => $barang->number,
            'subcategory_id' => $barang->subcategory_id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New',
            'specification' => 'Spec New',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tipe berhasil diperbarui.');

        $this->assertDatabaseHas('barangs', [
            'id' => $barang->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New',
            'specification' => 'Spec New',
        ]);
    }

    public function test_can_bulk_update_barangs(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        
        $barang1 = Barang::factory()->create([
            'name' => 'Nama Old 1',
            'specification' => 'Spec Old 1',
        ]);
        $barang2 = Barang::factory()->create([
            'name' => 'Nama Old 2',
            'specification' => 'Spec Old 2',
        ]);

        $newBrand = Brand::factory()->create();
        $newUom = Uom::factory()->create();
        $file = UploadedFile::fake()->image('barang.png');

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.bulk-update'), [
            'ids' => [$barang1->id, $barang2->id],
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
            'image_url' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 tipe terpilih berhasil diperbarui.');

        $this->assertDatabaseHas('barangs', [
            'id' => $barang1->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $this->assertDatabaseHas('barangs', [
            'id' => $barang2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'name' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $barang1->refresh();
        $this->assertNotNull($barang1->image_url);
        Storage::disk('local')->assertExists($barang1->image_url);
    }

    public function test_bulk_update_keeps_empty_inputs(): void
    {
        $user = User::factory()->create();
        
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang1 = Barang::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Nama Keep 1',
            'specification' => 'Spec Keep 1',
        ]);
        $barang2 = Barang::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'name' => 'Nama Keep 2',
            'specification' => 'Spec Keep 2',
        ]);

        // Bulk update sending only brand_id
        $newBrand = Brand::factory()->create();
        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.bulk-update'), [
            'ids' => [$barang1->id, $barang2->id],
            'brand_id' => $newBrand->id,
            // uom_id, nama and specification are not sent/empty
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('barangs', [
            'id' => $barang1->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $uom->id, // kept old uom
            'name' => 'Nama Keep 1', // kept old name
            'specification' => 'Spec Keep 1', // kept old spec
        ]);

        $this->assertDatabaseHas('barangs', [
            'id' => $barang2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $uom->id, // kept old uom
            'name' => 'Nama Keep 2', // kept old name
            'specification' => 'Spec Keep 2', // kept old spec
        ]);
    }

    public function test_can_bulk_destroy_barangs_without_lots(): void
    {
        $user = User::factory()->create();
        $barangs = Barang::factory()->count(3)->create();
        $ids = $barangs->pluck('id')->toArray();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.bulk-destroy'), [
            'ids' => $ids,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '3 barang terpilih berhasil dihapus.');

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('barangs', [
                'id' => $id,
            ]);
        }
    }

    public function test_cannot_bulk_destroy_barangs_with_any_lots(): void
    {
        $user = User::factory()->create();
        $barangWithoutLots = Barang::factory()->create();
        $lot = Lot::factory()->create([
            'barang_id' => Barang::factory()->create()->id,
        ]);
        $barangWithLots = $lot->barang;

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.bulk-destroy'), [
            'ids' => [$barangWithoutLots->id, $barangWithLots->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', "1 barang terpilih berhasil dihapus.\n1 barang tidak dapat dihapus karena memiliki LOT terkait.");

        $this->assertDatabaseMissing('barangs', [
            'id' => $barangWithoutLots->id,
        ]);
        $this->assertDatabaseHas('barangs', [
            'id' => $barangWithLots->id,
        ]);
    }

    public function test_media_route_requires_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('inventory/test.jpg', 'fake image content');

        // Unauthenticated request should be redirected to login
        $unauthResponse = $this->get('/media/inventory/test.jpg');
        $unauthResponse->assertRedirect(route('login'));

        // Authenticated request should serve the image
        $user = User::factory()->create();
        $authResponse = $this->actingAs($user)->get('/media/inventory/test.jpg');
        $authResponse->assertStatus(200);
        $authResponse->assertHeader('Cache-Control', 'max-age=86400, private');
    }

    public function test_can_deduct_consumable_stock_from_single_lot(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 100,
            'current_quantity' => 80,
            'date_of_receipt' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.update', $barang), [
            'number' => $barang->number,
            'subcategory_id' => $barang->subcategory_id,
            'brand_id' => $barang->brand_id,
            'uom_id' => $barang->uom_id,
            'name' => $barang->name,
            'available_stock' => 50,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tipe berhasil diperbarui.');

        $this->assertEquals(50, $lot->fresh()->current_quantity);

        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $lot->id,
            'action_type' => 'adjustment',
            'quantity_change' => -30,
        ]);
    }

    public function test_can_deduct_consumable_stock_fifo_across_multiple_lots(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        // Lot 1: Oldest lot with 20 available
        $oldLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 50,
            'current_quantity' => 20,
            'date_of_receipt' => now()->subDays(20),
        ]);

        // Lot 2: Newer lot with 40 available
        $newLot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 50,
            'current_quantity' => 40,
            'date_of_receipt' => now()->subDays(5),
        ]);

        // Total available = 20 + 40 = 60.
        // Deduct to 25 (deducting 35 total: 20 from oldLot, 15 from newLot).
        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.update', $barang), [
            'number' => $barang->number,
            'subcategory_id' => $barang->subcategory_id,
            'brand_id' => $barang->brand_id,
            'uom_id' => $barang->uom_id,
            'name' => $barang->name,
            'available_stock' => 25,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Tipe berhasil diperbarui.');

        // Old lot should be 0 (exhausted)
        $this->assertEquals(0, $oldLot->fresh()->current_quantity);
        // New lot should have 40 - 15 = 25 remaining
        $this->assertEquals(25, $newLot->fresh()->current_quantity);

        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $oldLot->id,
            'quantity_change' => -20,
        ]);
        $this->assertDatabaseHas('inventory_logs', [
            'barang_id' => $barang->id,
            'lot_id' => $newLot->id,
            'quantity_change' => -15,
        ]);
    }

    public function test_cannot_increase_available_stock_via_barang_update(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 50,
            'current_quantity' => 30,
        ]);

        // Total available is 30. Trying to set to 40 should fail validation.
        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.update', $barang), [
            'number' => $barang->number,
            'subcategory_id' => $barang->subcategory_id,
            'brand_id' => $barang->brand_id,
            'uom_id' => $barang->uom_id,
            'name' => $barang->name,
            'available_stock' => 40,
        ]);

        $response->assertSessionHasErrors('available_stock');
    }

    public function test_cannot_deduct_stock_for_non_consumable_item(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_consumable' => false]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create(['subcategory_id' => $subcategory->id]);

        Lot::factory()->create([
            'barang_id' => $barang->id,
            'initial_quantity' => 50,
            'current_quantity' => 30,
        ]);

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.update', $barang), [
            'number' => $barang->number,
            'subcategory_id' => $barang->subcategory_id,
            'brand_id' => $barang->brand_id,
            'uom_id' => $barang->uom_id,
            'name' => $barang->name,
            'available_stock' => 20,
        ]);

        $response->assertSessionHasErrors('available_stock');
    }
}
