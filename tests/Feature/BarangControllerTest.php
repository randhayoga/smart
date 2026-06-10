<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\AdmUser as User;
use App\Models\Master\Brand;
use App\Models\Master\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarangControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_destroy_barang_without_lots(): void
    {
        $user = User::factory()->create();
        $barang = Barang::factory()->create();

        $response = $this->actingAs($user)->delete(route('smart.inventory.barangs.destroy', $barang));

        $response->assertRedirect(route('smart.inventory'));
        $response->assertSessionHas('success', 'Barang berhasil dihapus.');
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

    public function test_can_bulk_update_barangs(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        
        $barang1 = Barang::factory()->create([
            'nama' => 'Nama Old 1',
            'specification' => 'Spec Old 1',
        ]);
        $barang2 = Barang::factory()->create([
            'nama' => 'Nama Old 2',
            'specification' => 'Spec Old 2',
        ]);

        $newBrand = Brand::factory()->create();
        $newUom = Uom::factory()->create();
        $file = UploadedFile::fake()->image('barang.png');

        $response = $this->actingAs($user)->put(route('smart.inventory.barangs.bulk-update'), [
            'ids' => [$barang1->id, $barang2->id],
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'nama' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
            'image_url' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Barang-barang terpilih berhasil diperbarui.');

        $this->assertDatabaseHas('barangs', [
            'id' => $barang1->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'nama' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $this->assertDatabaseHas('barangs', [
            'id' => $barang2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $newUom->id,
            'nama' => 'Nama New Bulk',
            'specification' => 'Spec New Bulk',
        ]);

        $barang1->refresh();
        $this->assertNotNull($barang1->image_url);
        Storage::disk('public')->assertExists($barang1->image_url);
    }

    public function test_bulk_update_keeps_empty_inputs(): void
    {
        $user = User::factory()->create();
        
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang1 = Barang::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'nama' => 'Nama Keep 1',
            'specification' => 'Spec Keep 1',
        ]);
        $barang2 = Barang::factory()->create([
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'nama' => 'Nama Keep 2',
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
            'nama' => 'Nama Keep 1', // kept old nama
            'specification' => 'Spec Keep 1', // kept old spec
        ]);

        $this->assertDatabaseHas('barangs', [
            'id' => $barang2->id,
            'brand_id' => $newBrand->id,
            'uom_id' => $uom->id, // kept old uom
            'nama' => 'Nama Keep 2', // kept old nama
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
        $lot = Lot::factory()->create();
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
}
