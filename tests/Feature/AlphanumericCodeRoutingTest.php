<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Alphanumeric Code Routing Feature Tests
 *
 * Verifies slugification and alphanumeric resolution across item detail, consumable stock, and barcode scan endpoints.
 */
class AlphanumericCodeRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): AdmUser
    {
        HrdEmployee::factory()->create(['employee_id' => '255578']);
        return AdmUser::factory()->create(['employee_id' => '255578']);
    }

    public function test_barang_get_route_key_is_alphanumeric_only(): void
    {
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'number' => 'ATK-HVS4-0001',
            'subcategory_id' => $subcategory->id,
        ]);

        $this->assertEquals('ATKHVS40001', $barang->getRouteKey());
        $this->assertStringEndsWith('/smart/inventory/ATKHVS40001', route('smart.inventory.show', $barang));
    }

    public function test_unit_get_route_key_is_alphanumeric_only(): void
    {
        $lot = Lot::factory()->create();
        $unit = Unit::create([
            'number' => '00001-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);

        $this->assertEquals('00001FURKKCFSPTRE26', $unit->getRouteKey());
        $this->assertStringEndsWith('/smart/scan/00001FURKKCFSPTRE26', route('smart.scan', $unit));
    }

    public function test_can_access_inventory_detail_using_alphanumeric_code(): void
    {
        $user = $this->createAdminUser();
        $category = Category::factory()->create();
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'number' => 'ATK-HVS4-0001',
            'name' => 'Kertas HVS',
            'subcategory_id' => $subcategory->id,
        ]);

        // 1. Using alphanumeric code
        $response = $this->actingAs($user)->get('/smart/inventory/ATKHVS40001');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DetailBarang')
            ->where('barang.id', $barang->id)
            ->where('barang.code', 'ATK-HVS4-0001')
        );

        // 2. Using original code with dashes
        $responseDash = $this->actingAs($user)->get('/smart/inventory/ATK-HVS4-0001');
        $responseDash->assertStatus(200);

        // 3. Using lowercase alphanumeric code
        $responseLower = $this->actingAs($user)->get('/smart/inventory/atkhvs40001');
        $responseLower->assertStatus(200);
    }

    public function test_can_access_stok_habis_pakai_using_alphanumeric_code(): void
    {
        $user = $this->createAdminUser();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'number' => 'BRG-HP-001',
            'subcategory_id' => $subcategory->id,
        ]);
        Lot::factory()->create([
            'barang_id' => $barang->id,
            'current_quantity' => 10,
        ]);

        // Access via alphanumeric code
        $response = $this->actingAs($user)->get('/smart/inventory/stok-habis-pakai/BRGHP001');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarStokHabisPakai')
            ->where('selectedBarangCode', 'BRG-HP-001')
        );
    }

    public function test_can_access_scan_using_alphanumeric_code(): void
    {
        $user = $this->createAdminUser();
        $lot = Lot::factory()->create();
        $unit = Unit::create([
            'number' => '00001-FUR-KK-CFS-PTRE-26',
            'lot_id' => $lot->id,
            'location_id' => $lot->location_id,
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'image_url' => 'inventory/lots/placeholder.jpg',
        ]);

        // 1. Using alphanumeric code
        $response = $this->actingAs($user)->get('/smart/scan/00001FURKKCFSPTRE26');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/MultiRoles/HasilPindai')
            ->where('asset.id', $unit->id)
            ->where('asset.number', '00001-FUR-KK-CFS-PTRE-26')
        );

        // 2. Using original code with dashes
        $responseDash = $this->actingAs($user)->get('/smart/scan/00001-FUR-KK-CFS-PTRE-26');
        $responseDash->assertStatus(200);

        // 3. Using lowercase alphanumeric code
        $responseLower = $this->actingAs($user)->get('/smart/scan/00001furkkcfsptre26');
        $responseLower->assertStatus(200);
    }
}
