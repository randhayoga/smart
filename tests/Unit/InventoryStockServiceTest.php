<?php

namespace Tests\Unit;

use App\Models\Inventory\Barang;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Unit;
use App\Models\Master\Brand;
use App\Models\Master\Category;
use App\Models\Master\Subcategory;
use App\Models\Master\Uom;
use App\Services\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryStockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryStockService();
    }

    public function test_get_available_stock_for_unit_tracked_barang(): void
    {
        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        $lot = Lot::factory()->create(['barang_id' => $barang->id]);

        Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia']);
        Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Tersedia']);
        Unit::factory()->create(['lot_id' => $lot->id, 'status' => 'Dipinjam']);

        $stock = $this->service->getAvailableStockForBarang($barang->id);
        $this->assertEquals(2, $stock);
    }

    public function test_get_available_stock_for_consumable_barang(): void
    {
        $cat = Category::factory()->create(['is_consumable' => true]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();
        $barang = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);

        Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 15]);
        Lot::factory()->create(['barang_id' => $barang->id, 'current_quantity' => 25]);

        $stock = $this->service->getAvailableStockForBarang($barang->id);
        $this->assertEquals(40, $stock);
    }

    public function test_get_batch_available_stock_computes_accurately(): void
    {
        $cat = Category::factory()->create(['is_consumable' => false]);
        $sub = Subcategory::factory()->create(['category_id' => $cat->id]);
        $brand = Brand::factory()->create();
        $uom = Uom::factory()->create();

        // Barang 1: Unit tracked
        $barang1 = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);
        $lot1 = Lot::factory()->create(['barang_id' => $barang1->id]);
        Unit::factory()->create(['lot_id' => $lot1->id, 'status' => 'Tersedia']);

        // Barang 2: Consumable
        $barang2 = Barang::factory()->create([
            'subcategory_id' => $sub->id,
            'brand_id' => $brand->id,
            'uom_id' => $uom->id,
        ]);
        Lot::factory()->create(['barang_id' => $barang2->id, 'current_quantity' => 10]);

        $items = [
            ['barang_id' => $barang1->id, 'subcategory_id' => $sub->id],
            ['barang_id' => $barang2->id, 'subcategory_id' => $sub->id],
        ];

        $stockMap = $this->service->getBatchAvailableStock($items);

        $this->assertEquals(1, $stockMap["barang_{$barang1->id}"]);
        $this->assertEquals(10, $stockMap["barang_{$barang2->id}"]);
    }
}
