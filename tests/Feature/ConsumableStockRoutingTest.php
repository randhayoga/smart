<?php

namespace Tests\Feature;

use App\Models\AdmUser;
use App\Models\HrdEmployee;
use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsumableStockRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): AdmUser
    {
        HrdEmployee::factory()->create(['employee_id' => '255578']);
        return AdmUser::factory()->create(['employee_id' => '255578']);
    }

    public function test_can_access_stok_habis_pakai_root(): void
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

        $response = $this->actingAs($user)->get(route('smart.inventory.stok-habis-pakai'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarStokHabisPakai')
            ->where('selectedBarangCode', null)
            ->has('barangs', 1)
            ->has('lots', 1)
        );
    }

    public function test_can_access_stok_habis_pakai_with_kode_tipe(): void
    {
        $user = $this->createAdminUser();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'number' => 'BRG-HP-002',
            'subcategory_id' => $subcategory->id,
        ]);
        Lot::factory()->create([
            'barang_id' => $barang->id,
            'current_quantity' => 5,
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.stok-habis-pakai', ['barang' => $barang->number]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarStokHabisPakai')
            ->where('selectedBarangCode', 'BRG-HP-002')
            ->has('barangs', 1)
        );
    }

    public function test_can_access_stok_habis_pakai_with_legacy_query_param(): void
    {
        $user = $this->createAdminUser();
        $category = Category::factory()->create(['is_consumable' => true]);
        $subcategory = Subcategory::factory()->create(['category_id' => $category->id]);
        $barang = Barang::factory()->create([
            'number' => 'BRG-HP-003',
            'subcategory_id' => $subcategory->id,
        ]);
        Lot::factory()->create([
            'barang_id' => $barang->id,
            'current_quantity' => 8,
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.stok-habis-pakai', ['barang_id' => $barang->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/ManajemenStok/DaftarStokHabisPakai')
            ->where('selectedBarangCode', 'BRG-HP-003')
            ->has('barangs', 1)
        );
    }
}
