<?php

namespace Tests\Feature\Admin\StockManagement;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetCreatedSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_endpoint_returns_units_with_created_at_sorted_newest_first(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $organizer = Organizer::firstOrCreate(['name' => 'CFS']);
        $category = Category::factory()->create(['name' => 'Aset']);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'AST',
            'name' => 'Aset',
        ]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'number' => 'AST-0001',
        ]);

        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
        ]);

        // Create older unit (3 days ago)
        $olderUnit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'AST-CFS-0001',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // Create newer unit (now)
        $newerUnit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'AST-CFS-0002',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.non-consumables'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/StockManagement/NonConsumableList')
            ->has('units_compact.rows', 2)
            ->where('units_compact.rows.0.0', $newerUnit->id)
            ->where('units_compact.rows.0.1', 'AST-CFS-0002')
            ->where('units_compact.rows.1.0', $olderUnit->id)
            ->where('units_compact.rows.1.1', 'AST-CFS-0001')
        );
    }

    public function test_assets_endpoint_excludes_unverified_units(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();
        $organizer = Organizer::firstOrCreate(['name' => 'CFS']);
        $category = Category::factory()->create(['name' => 'Aset']);
        $subcategory = Subcategory::factory()->create([
            'category_id' => $category->id,
            'code' => 'ASTX',
            'name' => 'Aset X',
        ]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        $barang = Barang::factory()->create([
            'subcategory_id' => $subcategory->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
            'number' => 'ASTX-0002',
        ]);

        $lot = Lot::factory()->create([
            'barang_id' => $barang->id,
            'organizer_id' => $organizer->id,
        ]);

        // Verified unit
        $verifiedUnit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'ASTX-CFS-1001',
            'status' => 'Tersedia',
            'condition' => 'Bagus',
        ]);

        // Unverified unit (Belum Diverifikasi)
        $unverifiedUnit = Unit::factory()->create([
            'lot_id' => $lot->id,
            'location_id' => $location->id,
            'number' => 'ASTX-CFS-1002',
            'status' => 'Belum Diverifikasi',
            'condition' => 'Bagus',
        ]);

        $response = $this->actingAs($user)->get(route('smart.inventory.non-consumables'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Smart/Admin/StockManagement/NonConsumableList')
            ->has('units_compact.rows', 1)
            ->where('units_compact.rows.0.0', $verifiedUnit->id)
        );
    }
}
