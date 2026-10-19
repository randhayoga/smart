<?php

namespace Tests\Feature;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use Database\Seeders\DummyConsumableDatabaseSeeder;
use Database\Seeders\DummyNonConsumableDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummySeederSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumable_seeder_creates_consumable_records_and_no_units(): void
    {
        $this->seed(DummyConsumableDatabaseSeeder::class);

        // Category ATK must exist
        $atkCategory = Category::where('code', 'ATK')->first();
        $this->assertNotNull($atkCategory);

        // Subcategories must be flagged as consumable
        $subcategories = Subcategory::where('category_id', $atkCategory->id)->get();
        $this->assertCount(2, $subcategories);
        foreach ($subcategories as $sub) {
            $this->assertTrue((bool)$sub->is_consumable);
        }

        // Consumable barangs must exist and have is_consumable = true
        $hvsBarang = Barang::where('number', 'ATK-HVS4-0001')->first();
        $this->assertNotNull($hvsBarang);
        $this->assertTrue($hvsBarang->is_consumable);

        $penBarang = Barang::where('number', 'ATK-PLPH-0001')->first();
        $this->assertNotNull($penBarang);
        $this->assertTrue($penBarang->is_consumable);

        // Consumable lots must exist with quantity > 0, image_url, location_id, and unit_price
        $lots = Lot::whereIn('number', [
            'LOT-0001-26-ATK-HVS4-0001',
            'LOT-0002-26-ATK-HVS4-0001',
            'LOT-0001-26-ATK-PLPH-0001',
            'LOT-0002-26-ATK-PLPH-0002',
        ])->get();
        $this->assertCount(4, $lots);

        foreach ($lots as $lot) {
            $this->assertGreaterThan(0, $lot->initial_quantity);
            $this->assertGreaterThan(0, $lot->current_quantity);
            $this->assertNotNull($lot->burden);
            $this->assertNotNull($lot->location_id);
            $this->assertNotNull($lot->unit_price);
            $this->assertNotNull($lot->image_url);
            $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($lot->image_url));
        }

        // Consumables must NEVER generate unit records
        $this->assertEquals(0, Unit::count());
    }

    public function test_consumable_seeder_is_idempotent(): void
    {
        $this->seed(DummyConsumableDatabaseSeeder::class);
        $categoryCount1 = Category::where('code', 'ATK')->count();
        $subcatCount1 = Subcategory::where('code', 'like', 'ATK%')->count();
        $barangCount1 = Barang::where('number', 'like', 'ATK%')->count();
        $lotCount1 = Lot::where('number', 'like', '%ATK%')->count();

        // Seed second time
        $this->seed(DummyConsumableDatabaseSeeder::class);

        $this->assertEquals($categoryCount1, Category::where('code', 'ATK')->count());
        $this->assertEquals($subcatCount1, Subcategory::where('code', 'like', 'ATK%')->count());
        $this->assertEquals($barangCount1, Barang::where('number', 'like', 'ATK%')->count());
        $this->assertEquals($lotCount1, Lot::where('number', 'like', '%ATK%')->count());
        $this->assertEquals(0, Unit::count());
    }

    public function test_non_consumable_seeder_creates_non_consumable_records_and_units(): void
    {
        $this->seed(DummyNonConsumableDatabaseSeeder::class);

        $subcategories = Subcategory::whereIn('code', ['FUR-KK', 'FUR-MK', 'COMP-NB', 'KEN-MO'])->get();
        foreach ($subcategories as $sub) {
            $this->assertFalse((bool)$sub->is_consumable);
        }

        $laptop = Barang::where('number', 'COMP-NB-0001')->first();
        $this->assertNotNull($laptop);
        $this->assertFalse($laptop->is_consumable);

        $laptopLot = Lot::where('number', 'LOT-0001-26-COMP-NB-0001')->first();
        $this->assertNotNull($laptopLot);
        $this->assertEquals(0, $laptopLot->initial_quantity);

        $units = Unit::all();
        $this->assertGreaterThan(0, $units->count());
    }
}
